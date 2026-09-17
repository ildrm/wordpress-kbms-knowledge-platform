<?php

declare(strict_types=1);

namespace KBMS\Permissions;

use KBMS\Core\Hookable;

final class AccessController implements Hookable {

	private AuthorizationInterface $authorization;

	public function __construct( AuthorizationInterface $authorization ) {
		$this->authorization = $authorization;
	}

	public function registerHooks(): void {
		add_filter( 'map_meta_cap', array( $this, 'mapMetaCapability' ), 20, 4 );
		add_action( 'pre_get_posts', array( $this, 'constrainKnowledgeQuery' ), 20 );
		add_filter( 'posts_clauses', array( $this, 'constrainMixedQuery' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'protectSingularRequest' ), 1 );
		add_filter( 'rest_pre_dispatch', array( $this, 'protectCoreRestRoute' ), 10, 3 );
	}

	/**
	 * @param list<string>     $caps
	 * @param array<int,mixed> $args
	 * @return list<string>
	 */
	public function mapMetaCapability( array $caps, string $capability, int $userId, array $args ): array {
		$actions = array(
			'read_post'        => 'read',
			'edit_post'        => 'edit',
			'delete_post'      => 'delete',
			'read_kbms_item'   => 'read',
			'edit_kbms_item'   => 'edit',
			'delete_kbms_item' => 'delete',
		);
		if ( ! isset( $actions[ $capability ] ) || ! isset( $args[0] ) ) {
			return $caps;
		}

		$postId = (int) $args[0];
		if ( in_array( $capability, array( 'read_post', 'edit_post', 'delete_post' ), true )
			&& get_post_type( $postId ) !== 'kbms_item' ) {
			if ( get_post_type( $postId ) !== 'attachment' ) {
				return $caps;
			}
			$parentId = (int) wp_get_post_parent_id( $postId );
			if ( $parentId <= 0 || get_post_type( $parentId ) !== 'kbms_item' ) {
				return $caps;
			}
			$postId = $parentId;
		}
		if ( in_array( $actions[ $capability ], array( 'edit', 'delete' ), true )
			&& absint( get_post_meta( $postId, '_kbms_space_id', true ) ) === 0
			&& (int) get_post_field( 'post_author', $postId ) === $userId ) {
			// The author must be able to assign the first governed space. Until then,
			// the item remains absent from the permission-aware index and unreadable.
			return array( $actions[ $capability ] === 'edit' ? 'edit_kbms_items' : 'delete_kbms_items' );
		}
		return $this->authorization->can( $actions[ $capability ], $postId, $userId )
			? array( 'exist' )
			: array( 'do_not_allow' );
	}

	/** @param \WP_Query $query */
	public function constrainKnowledgeQuery( $query ): void {
		$postType    = $query->get( 'post_type' );
		$isKnowledge = $postType === 'kbms_item'
			|| ( is_array( $postType ) && in_array( 'kbms_item', $postType, true ) );
		if ( ! $isKnowledge ) {
			return;
		}

		$constrained = $this->authorization->constrainQueryArgs(
			array(
				'post__in' => $query->get( 'post__in' ),
			),
			get_current_user_id()
		);
		$query->set( 'post__in', $constrained['post__in'] );
		if ( isset( $constrained['post_status'] ) ) {
			$query->set( 'post_status', $constrained['post_status'] );
		}
	}

	/**
	 * Prevents restricted knowledge from leaking through generic/mixed searches and counts.
	 *
	 * @param array<string,string> $clauses
	 * @param \WP_Query            $query
	 * @return array<string,string>
	 */
	public function constrainMixedQuery( array $clauses, $query ): array {
		$postType = $query->get( 'post_type' );
		if ( $postType === 'kbms_item' ) {
			return $clauses;
		}
		if ( is_array( $postType ) && ! in_array( 'kbms_item', $postType, true ) ) {
			return $clauses;
		}
		if ( is_string( $postType ) && $postType !== '' && $postType !== 'any' ) {
			return $clauses;
		}

		$userId = get_current_user_id();
		if ( $userId > 0 && ( user_can( $userId, 'manage_kbms' ) || is_super_admin( $userId ) ) ) {
			return $clauses;
		}

		global $wpdb;
		$metaTable    = $wpdb->prefix . 'kbms_knowledge_meta';
		$spacesTable  = $wpdb->prefix . 'kbms_spaces';
		$membersTable = $wpdb->prefix . 'kbms_space_members';
		if ( $userId > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses the trusted WordPress prefix.
			$access = $wpdb->prepare(
				"(s.visibility IN ('public','internal') OR km.owner_id = %d OR EXISTS (
                    SELECT 1 FROM {$membersTable} sm WHERE sm.space_id = s.id AND sm.user_id = %d
                ))",
				$userId,
				$userId
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$access = "s.visibility = 'public'";
		}
		$clauses['where'] .= " AND ({$wpdb->posts}.post_type <> 'kbms_item' OR EXISTS (
            SELECT 1 FROM {$metaTable} km
            INNER JOIN {$spacesTable} s ON s.id = km.space_id
            WHERE km.post_id = {$wpdb->posts}.ID AND {$access}
        ))";
		$clauses['where'] .= " AND ({$wpdb->posts}.post_type <> 'attachment'
            OR NOT EXISTS (
                SELECT 1 FROM {$wpdb->posts} kbms_parent
                WHERE kbms_parent.ID = {$wpdb->posts}.post_parent AND kbms_parent.post_type = 'kbms_item'
            )
            OR EXISTS (
                SELECT 1 FROM {$metaTable} km
                INNER JOIN {$spacesTable} s ON s.id = km.space_id
                WHERE km.post_id = {$wpdb->posts}.post_parent AND {$access}
            ))";
		return $clauses;
	}

	public function protectSingularRequest(): void {
		if ( ! is_singular( 'kbms_item' ) && ! is_attachment() ) {
			return;
		}
		$postId = (int) get_queried_object_id();
		if ( is_attachment() ) {
			$parentId = (int) wp_get_post_parent_id( $postId );
			if ( $parentId <= 0 || get_post_type( $parentId ) !== 'kbms_item' ) {
				return;
			}
			$postId = $parentId;
		}
		if ( $this->authorization->can( 'read', $postId, get_current_user_id() ) ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Enforces space rules on the core wp/v2 endpoint in addition to WP's post caps.
	 *
	 * @param mixed            $result
	 * @param mixed            $server
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public function protectCoreRestRoute( $result, $server, $request ) {
		unset( $server );
		if ( $result !== null ) {
			return $result;
		}
		$route = $request->get_route();
		if ( preg_match( '~^/wp/v2/kbms_item/(\d+)(?:/|$)~', $route, $matches ) ) {
			$resourceId = (int) $matches[1];
		} elseif ( preg_match( '~^/wp/v2/media/(\d+)(?:/|$)~', $route, $matches ) ) {
			$attachmentId = (int) $matches[1];
			$resourceId   = (int) wp_get_post_parent_id( $attachmentId );
			if ( $resourceId <= 0 || get_post_type( $resourceId ) !== 'kbms_item' ) {
				return $result;
			}
		} else {
			return $result;
		}

		$method = strtoupper( $request->get_method() );
		$action = $method === 'GET' || $method === 'HEAD'
			? 'read'
			: ( $method === 'DELETE' ? 'delete' : 'edit' );
		if ( ! $this->authorization->can( $action, $resourceId, get_current_user_id() ) ) {
			return new \WP_Error( 'kbms_forbidden', __( 'You cannot access this knowledge item.', 'wp-kbms' ), array( 'status' => 403 ) );
		}
		return $result;
	}
}
