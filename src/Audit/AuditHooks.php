<?php

declare(strict_types=1);

namespace KBMS\Audit;

use KBMS\Core\Hookable;
use KBMS\Knowledge\KnowledgePostType;

final class AuditHooks implements Hookable {

	private AuditLogger $audit;

	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	public function registerHooks(): void {
		add_action( 'save_post_' . KnowledgePostType::POST_TYPE, array( $this, 'postSaved' ), 30, 3 );
		add_action( 'before_delete_post', array( $this, 'postDeleted' ), 10, 2 );
		add_action( 'kbms_space_created', array( $this, 'spaceCreated' ) );
		add_action( 'kbms_space_updated', array( $this, 'spaceUpdated' ), 10, 2 );
		add_action( 'kbms_space_membership_changed', array( $this, 'membershipChanged' ), 10, 3 );
		add_action( 'kbms_space_membership_removed', array( $this, 'membershipRemoved' ), 10, 2 );
	}

	/** @param \WP_Post $post */
	public function postSaved( int $postId, $post, bool $update ): void {
		if ( wp_is_post_revision( $postId ) || wp_is_post_autosave( $postId ) ) {
			return;
		}
		$this->audit->record(
			$update ? 'knowledge.updated' : 'knowledge.created',
			'knowledge',
			(string) $postId,
			array(
				'status' => $post->post_status,
			)
		);
	}

	/** @param \WP_Post $post */
	public function postDeleted( int $postId, $post ): void {
		if ( $post->post_type === KnowledgePostType::POST_TYPE ) {
			$this->audit->record( 'knowledge.deleted', 'knowledge', (string) $postId );
		}
	}

	/** @param \KBMS\Knowledge\Space $space */
	public function spaceCreated( $space ): void {
		$this->audit->record( 'space.created', 'space', (string) $space->id, array( 'visibility' => $space->visibility ) );
	}

	/** @param \KBMS\Knowledge\Space $space @param \KBMS\Knowledge\Space $previous */
	public function spaceUpdated( $space, $previous ): void {
		$this->audit->record(
			'space.updated',
			'space',
			(string) $space->id,
			array(
				'old_visibility' => $previous->visibility,
				'new_visibility' => $space->visibility,
			)
		);
	}

	public function membershipChanged( int $spaceId, int $memberUserId, string $role ): void {
		$this->audit->record(
			'space.membership_changed',
			'space',
			(string) $spaceId,
			array(
				'member_user_id' => $memberUserId,
				'member_role'    => $role,
			)
		);
	}

	public function membershipRemoved( int $spaceId, int $memberUserId ): void {
		$this->audit->record(
			'space.membership_removed',
			'space',
			(string) $spaceId,
			array(
				'member_user_id' => $memberUserId,
			)
		);
	}
}
