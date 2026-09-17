<?php

declare(strict_types=1);

namespace KBMS\Knowledge;

use KBMS\Audit\AuditLogger;
use KBMS\Core\Hookable;
use KBMS\Permissions\AuthorizationInterface;
use WP_Error;
use wpdb;

final class VerificationService implements Hookable {

	private wpdb $db;
	private AuthorizationInterface $authorization;
	private AuditLogger $audit;

	public function __construct( wpdb $db, AuthorizationInterface $authorization, AuditLogger $audit ) {
		$this->db            = $db;
		$this->authorization = $authorization;
		$this->audit         = $audit;
	}

	public function registerHooks(): void {
		add_action( 'kbms_daily_maintenance', array( $this, 'markDueItemsStale' ), 10 );
	}

	/** @return true|WP_Error */
	public function verify( int $postId, int $actorId, int $reviewIntervalDays = 180 ) {
		if ( ! $this->authorization->can( 'verify', $postId, $actorId ) ) {
			return new WP_Error( 'kbms_verify_forbidden', __( 'You cannot verify this knowledge item.', 'wp-kbms' ), array( 'status' => 403 ) );
		}
		$reviewIntervalDays = max( 1, min( 3650, $reviewIntervalDays ) );
		$verifiedAt         = current_time( 'mysql', true );
		$reviewAt           = gmdate( 'Y-m-d H:i:s', time() + ( $reviewIntervalDays * DAY_IN_SECONDS ) );
		update_post_meta( $postId, '_kbms_verification_status', 'verified' );
		update_post_meta( $postId, '_kbms_verified_at', $verifiedAt );
		update_post_meta( $postId, '_kbms_review_at', $reviewAt );
		update_post_meta( $postId, '_kbms_reviewer_id', $actorId );

		$this->audit->record(
			'knowledge.verified',
			'knowledge',
			(string) $postId,
			array(
				'review_at' => $reviewAt,
			),
			$actorId
		);
		do_action( 'kbms_knowledge_verified', $postId, $actorId, $reviewAt );
		return true;
	}

	public function markDueItemsStale(): int {
		$table   = $this->db->prefix . 'kbms_knowledge_meta';
		$postIds = $this->db->get_col(
			$this->db->prepare(
				"SELECT post_id FROM {$table}
             WHERE verification_status = 'verified' AND review_at IS NOT NULL AND review_at <= %s
             LIMIT 500",
				current_time( 'mysql', true )
			)
		);
		$count   = 0;
		foreach ( (array) $postIds as $postId ) {
			$postId = (int) $postId;
			update_post_meta( $postId, '_kbms_verification_status', 'stale' );
			$this->audit->record( 'knowledge.stale', 'knowledge', (string) $postId, array( 'reason' => 'review_due' ), 0 );
			++$count;
		}
		return $count;
	}
}
