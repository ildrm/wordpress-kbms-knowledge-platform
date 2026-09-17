<?php

declare(strict_types=1);

namespace KBMS\Permissions;

use wpdb;

final class AuthorizationService implements AuthorizationInterface {

	private const ROLE_LEVEL = array(
		'viewer'      => 10,
		'contributor' => 20,
		'reviewer'    => 30,
		'admin'       => 40,
	);

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	public function can( string $action, int $resourceId, int $userId ): bool {
		if ( $resourceId <= 0 || $userId < 0 ) {
			return false;
		}

		if ( $userId > 0 && ( user_can( $userId, 'manage_kbms' ) || is_super_admin( $userId ) ) ) {
			return true;
		}

		$resource = $this->resourceContext( $resourceId );
		if ( $resource === null ) {
			return $this->canAccessUnindexedDraft( $action, $resourceId, $userId );
		}

		$memberRole = $userId > 0 ? $this->memberRole( (int) $resource['space_id'], $userId ) : null;
		$isOwner    = $userId > 0 && (int) $resource['owner_id'] === $userId;

		switch ( $action ) {
			case 'read':
			case 'view':
				return $this->canRead( $resource, $userId, $memberRole, $isOwner );
			case 'export':
				return $userId > 0
					&& user_can( $userId, 'export_kbms_items' )
					&& $this->canRead( $resource, $userId, $memberRole, $isOwner );
			case 'ai':
				return $userId > 0
					&& user_can( $userId, 'use_kbms_ai' )
					&& $this->canRead( $resource, $userId, $memberRole, $isOwner );
			case 'edit':
				return $userId > 0
					&& user_can( $userId, 'edit_kbms_items' )
					&& ( $isOwner || $this->roleAtLeast( $memberRole, 'contributor' ) );
			case 'delete':
				return $userId > 0
					&& user_can( $userId, 'delete_kbms_items' )
					&& ( $isOwner || $this->roleAtLeast( $memberRole, 'admin' ) );
			case 'publish':
				return $userId > 0
					&& user_can( $userId, 'publish_kbms_items' )
					&& $this->roleAtLeast( $memberRole, 'admin' );
			case 'review':
				return $userId > 0
					&& user_can( $userId, 'review_kbms_items' )
					&& $this->roleAtLeast( $memberRole, 'reviewer' );
			case 'verify':
				return $userId > 0
					&& user_can( $userId, 'verify_kbms_items' )
					&& $this->roleAtLeast( $memberRole, 'reviewer' );
			case 'manage':
				return $userId > 0 && $this->roleAtLeast( $memberRole, 'admin' );
			default:
				return (bool) apply_filters( 'kbms_authorization_decision', false, $action, $resourceId, $userId, $resource );
		}
	}

	private function canAccessUnindexedDraft( string $action, int $resourceId, int $userId ): bool {
		$post = get_post( $resourceId );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'kbms_item' || $userId <= 0 ) {
			return false;
		}
		if ( user_can( $userId, 'manage_kbms' ) || is_super_admin( $userId ) ) {
			return true;
		}
		$isOwner = (int) $post->post_author === $userId;
		if ( in_array( $action, array( 'edit', 'delete' ), true ) ) {
			return $isOwner && user_can( $userId, $action . '_kbms_items' );
		}
		return in_array( $action, array( 'read', 'view' ), true )
			&& $post->post_status !== 'publish'
			&& $isOwner
			&& user_can( $userId, 'edit_kbms_items' );
	}

	public function constrainQueryArgs( array $args, int $userId ): array {
		$args['post_type'] = 'kbms_item';

		if ( $userId > 0 && ( user_can( $userId, 'manage_kbms' ) || is_super_admin( $userId ) ) ) {
			return $args;
		}

		$metaTable    = $this->db->prefix . 'kbms_knowledge_meta';
		$spacesTable  = $this->db->prefix . 'kbms_spaces';
		$membersTable = $this->db->prefix . 'kbms_space_members';

		if ( $userId > 0 ) {
			$sql = $this->db->prepare(
				"SELECT DISTINCT km.post_id
                 FROM {$metaTable} km
                 INNER JOIN {$spacesTable} s ON s.id = km.space_id
                 LEFT JOIN {$membersTable} sm ON sm.space_id = s.id AND sm.user_id = %d
                 WHERE s.visibility IN ('public','internal') OR sm.user_id IS NOT NULL OR km.owner_id = %d",
				$userId,
				$userId
			);
		} else {
			$sql                 = "SELECT km.post_id
                    FROM {$metaTable} km
                    INNER JOIN {$spacesTable} s ON s.id = km.space_id
                    WHERE s.visibility = 'public'";
			$args['post_status'] = 'publish';
		}

		$allowed = array_map( 'intval', (array) $this->db->get_col( $sql ) );
		if ( isset( $args['post__in'] ) && is_array( $args['post__in'] ) && $args['post__in'] !== array() ) {
			$requested = array_map( 'intval', $args['post__in'] );
			$allowed   = array_values( array_intersect( $allowed, $requested ) );
		}

		$args['post__in'] = $allowed !== array() ? $allowed : array( 0 );
		return $args;
	}

	/** @return array<string,mixed>|null */
	private function resourceContext( int $postId ): ?array {
		$metaTable   = $this->db->prefix . 'kbms_knowledge_meta';
		$spacesTable = $this->db->prefix . 'kbms_spaces';
		$sql         = $this->db->prepare(
			"SELECT km.post_id, km.space_id, km.owner_id, km.workflow_state, s.visibility, p.post_status
             FROM {$metaTable} km
             INNER JOIN {$spacesTable} s ON s.id = km.space_id
             INNER JOIN {$this->db->posts} p ON p.ID = km.post_id
             WHERE km.post_id = %d LIMIT 1",
			$postId
		);
		$row         = $this->db->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	private function memberRole( int $spaceId, int $userId ): ?string {
		$table = $this->db->prefix . 'kbms_space_members';
		$role  = $this->db->get_var(
			$this->db->prepare(
				"SELECT member_role FROM {$table} WHERE space_id = %d AND user_id = %d LIMIT 1",
				$spaceId,
				$userId
			)
		);
		return is_string( $role ) ? $role : null;
	}

	/** @param array<string,mixed> $resource */
	private function canRead( array $resource, int $userId, ?string $memberRole, bool $isOwner ): bool {
		if ( $resource['post_status'] === 'publish' ) {
			if ( $resource['visibility'] === 'public' ) {
				return true;
			}
			if ( $resource['visibility'] === 'internal' && $userId > 0 ) {
				return true;
			}
			return $userId > 0 && ( $isOwner || $memberRole !== null );
		}
		return $userId > 0
			&& user_can( $userId, 'edit_kbms_items' )
			&& ( $isOwner || $this->roleAtLeast( $memberRole, 'contributor' ) );
	}

	private function roleAtLeast( ?string $actual, string $required ): bool {
		return $actual !== null
			&& isset( self::ROLE_LEVEL[ $actual ], self::ROLE_LEVEL[ $required ] )
			&& self::ROLE_LEVEL[ $actual ] >= self::ROLE_LEVEL[ $required ];
	}
}
