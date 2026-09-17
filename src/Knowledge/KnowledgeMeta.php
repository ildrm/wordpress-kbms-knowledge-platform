<?php

declare(strict_types=1);

namespace KBMS\Knowledge;

use KBMS\Core\Hookable;

final class KnowledgeMeta implements Hookable {

	/** @var array<string,array<string,mixed>> */
	private const SCHEMA = array(
		'_kbms_space_id'            => array(
			'type'    => 'integer',
			'default' => 0,
		),
		'_kbms_owner_id'            => array(
			'type'    => 'integer',
			'default' => 0,
		),
		'_kbms_reviewer_id'         => array(
			'type'    => 'integer',
			'default' => 0,
		),
		'_kbms_workflow_id'         => array(
			'type'    => 'integer',
			'default' => 0,
		),
		'_kbms_workflow_state'      => array(
			'type'    => 'string',
			'default' => 'draft',
		),
		'_kbms_verification_status' => array(
			'type'    => 'string',
			'default' => 'unverified',
		),
		'_kbms_confidentiality'     => array(
			'type'    => 'string',
			'default' => 'internal',
		),
		'_kbms_risk_level'          => array(
			'type'    => 'string',
			'default' => 'low',
		),
		'_kbms_language'            => array(
			'type'    => 'string',
			'default' => '',
		),
		'_kbms_review_at'           => array(
			'type'    => 'string',
			'default' => '',
		),
		'_kbms_expires_at'          => array(
			'type'    => 'string',
			'default' => '',
		),
		'_kbms_verified_at'         => array(
			'type'    => 'string',
			'default' => '',
		),
		'_kbms_attributes'          => array(
			'type'    => 'object',
			'default' => array(),
		),
	);

	public function registerHooks(): void {
		add_action( 'init', array( $this, 'registerMeta' ), 6 );
		add_action( 'save_post_' . KnowledgePostType::POST_TYPE, array( $this, 'synchronize' ), 20, 3 );
		add_action( 'added_post_meta', array( $this, 'metaChanged' ), 20, 4 );
		add_action( 'updated_post_meta', array( $this, 'metaChanged' ), 20, 4 );
		add_action( 'deleted_post', array( $this, 'deleteIndex' ), 10, 2 );
	}

	public function registerMeta(): void {
		foreach ( self::SCHEMA as $key => $schema ) {
			register_post_meta(
				KnowledgePostType::POST_TYPE,
				$key,
				array(
					'type'              => $schema['type'],
					'single'            => true,
					'default'           => $schema['default'],
					'show_in_rest'      => true,
					'sanitize_callback' => array( $this, 'sanitizeMeta' ),
					'auth_callback'     => array( $this, 'authorizeMeta' ),
				)
			);
		}
	}

	/** @param mixed $value @return mixed */
	public function sanitizeMeta( $value, string $key ) {
		switch ( $key ) {
			case '_kbms_space_id':
				return $this->sanitizeSpaceId( absint( $value ) );
			case '_kbms_owner_id':
			case '_kbms_reviewer_id':
			case '_kbms_workflow_id':
				return absint( $value );
			case '_kbms_workflow_state':
				return sanitize_key( (string) $value );
			case '_kbms_verification_status':
				return in_array( $value, array( 'unverified', 'verified', 'stale', 'rejected' ), true ) ? $value : 'unverified';
			case '_kbms_confidentiality':
				return in_array( $value, array( 'public', 'internal', 'confidential', 'restricted' ), true ) ? $value : 'internal';
			case '_kbms_risk_level':
				return in_array( $value, array( 'low', 'medium', 'high', 'critical' ), true ) ? $value : 'low';
			case '_kbms_language':
				return substr( sanitize_text_field( (string) $value ), 0, 20 );
			case '_kbms_review_at':
			case '_kbms_expires_at':
			case '_kbms_verified_at':
				return $this->sanitizeDate( (string) $value );
			case '_kbms_attributes':
				return is_array( $value ) ? $this->sanitizeAttributes( $value ) : array();
			default:
				return '';
		}
	}

	/** @param mixed ...$ignored */
	public function authorizeMeta( bool $allowed, string $key, int $postId, int $userId = 0, ...$ignored ): bool {
		unset( $allowed, $ignored );
		$userId = $userId !== 0 ? $userId : get_current_user_id();
		if ( $postId <= 0 || ! user_can( $userId, 'edit_post', $postId ) ) {
			return false;
		}
		if ( in_array( $key, array( '_kbms_owner_id', '_kbms_reviewer_id' ), true ) ) {
			return user_can( $userId, 'manage_kbms_spaces' ) || user_can( $userId, 'review_kbms_items' );
		}
		if ( in_array( $key, array( '_kbms_workflow_id', '_kbms_workflow_state' ), true ) ) {
			return user_can( $userId, 'manage_kbms_workflows' );
		}
		if ( in_array( $key, array( '_kbms_verification_status', '_kbms_verified_at' ), true ) ) {
			return user_can( $userId, 'verify_kbms_items' );
		}
		return true;
	}

	/** @param \WP_Post $post */
	public function synchronize( int $postId, $post, bool $update ): void {
		unset( $update );
		if ( wp_is_post_revision( $postId ) || wp_is_post_autosave( $postId ) || $post->post_type !== KnowledgePostType::POST_TYPE ) {
			return;
		}

		$this->upsertIndex( $postId );
	}

	/** @param mixed $value */
	public function metaChanged( int $metaId, int $postId, string $key, $value ): void {
		unset( $metaId, $value );
		if ( isset( self::SCHEMA[ $key ] ) && get_post_type( $postId ) === KnowledgePostType::POST_TYPE ) {
			$this->upsertIndex( $postId );
		}
	}

	/** @param \WP_Post $post */
	public function deleteIndex( int $postId, $post ): void {
		if ( $post->post_type !== KnowledgePostType::POST_TYPE ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'kbms_knowledge_meta', array( 'post_id' => $postId ), array( '%d' ) );
	}

	private function upsertIndex( int $postId ): void {
		global $wpdb;

		$spaceId = absint( get_post_meta( $postId, '_kbms_space_id', true ) );
		if ( $spaceId <= 0 ) {
			// Missing governance context is intentionally not indexed or discoverable.
			$wpdb->delete( $wpdb->prefix . 'kbms_knowledge_meta', array( 'post_id' => $postId ), array( '%d' ) );
			return;
		}

		$ownerId = absint( get_post_meta( $postId, '_kbms_owner_id', true ) );
		if ( $ownerId <= 0 ) {
			$ownerId = (int) get_post_field( 'post_author', $postId );
		}
		$attributes = get_post_meta( $postId, '_kbms_attributes', true );
		$table      = $wpdb->prefix . 'kbms_knowledge_meta';
		$sql        = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the trusted WordPress prefix.
			"INSERT INTO {$table}
             (post_id,space_id,owner_id,reviewer_id,workflow_id,workflow_state,verification_status,confidentiality,risk_level,language,review_at,expires_at,verified_at,version,attributes,updated_at)
             VALUES (%d,%d,%d,%d,NULLIF(%d,0),%s,%s,%s,%s,%s,NULLIF(%s,''),NULLIF(%s,''),NULLIF(%s,''),1,%s,%s)
             ON DUPLICATE KEY UPDATE
             space_id=VALUES(space_id), owner_id=VALUES(owner_id), reviewer_id=VALUES(reviewer_id),
             workflow_id=VALUES(workflow_id), workflow_state=VALUES(workflow_state),
             verification_status=VALUES(verification_status), confidentiality=VALUES(confidentiality),
             risk_level=VALUES(risk_level), language=VALUES(language), review_at=VALUES(review_at),
             expires_at=VALUES(expires_at), verified_at=VALUES(verified_at),
             version=version+1, attributes=VALUES(attributes), updated_at=VALUES(updated_at)",
			$postId,
			$spaceId,
			$ownerId,
			absint( get_post_meta( $postId, '_kbms_reviewer_id', true ) ),
			absint( get_post_meta( $postId, '_kbms_workflow_id', true ) ),
			sanitize_key( (string) get_post_meta( $postId, '_kbms_workflow_state', true ) ) !== '' ? sanitize_key( (string) get_post_meta( $postId, '_kbms_workflow_state', true ) ) : 'draft',
			(string) get_post_meta( $postId, '_kbms_verification_status', true ) !== '' ? (string) get_post_meta( $postId, '_kbms_verification_status', true ) : 'unverified',
			(string) get_post_meta( $postId, '_kbms_confidentiality', true ) !== '' ? (string) get_post_meta( $postId, '_kbms_confidentiality', true ) : 'internal',
			(string) get_post_meta( $postId, '_kbms_risk_level', true ) !== '' ? (string) get_post_meta( $postId, '_kbms_risk_level', true ) : 'low',
			(string) get_post_meta( $postId, '_kbms_language', true ),
			(string) get_post_meta( $postId, '_kbms_review_at', true ),
			(string) get_post_meta( $postId, '_kbms_expires_at', true ),
			(string) get_post_meta( $postId, '_kbms_verified_at', true ),
			wp_json_encode( is_array( $attributes ) ? $attributes : array() ),
			current_time( 'mysql', true )
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above with all values parameterized.
		do_action( 'kbms_knowledge_indexed', $postId );
	}

	private function sanitizeDate( string $value ): string {
		if ( $value === '' ) {
			return '';
		}
		$timestamp = strtotime( $value );
		return $timestamp === false ? '' : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	private function sanitizeSpaceId( int $spaceId ): int {
		$userId = get_current_user_id();
		if ( $spaceId <= 0 || $userId <= 0 ) {
			return 0;
		}
		if ( user_can( $userId, 'manage_kbms_spaces' ) || user_can( $userId, 'manage_kbms' ) ) {
			return $spaceId;
		}

		global $wpdb;
		$spaces  = $wpdb->prefix . 'kbms_spaces';
		$members = $wpdb->prefix . 'kbms_space_members';
		$allowed = $wpdb->get_var(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Both table names use the trusted WordPress prefix.
			$wpdb->prepare(
				"SELECT s.id FROM {$spaces} s
             LEFT JOIN {$members} sm ON sm.space_id = s.id AND sm.user_id = %d
             WHERE s.id = %d AND (s.owner_id = %d OR sm.member_role IN ('contributor','reviewer','admin'))
             LIMIT 1",
				$userId,
				$spaceId,
				$userId
			)
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return $allowed !== null ? $spaceId : 0;
	}

	/** @param array<mixed> $value @return array<mixed> */
	private function sanitizeAttributes( array $value ): array {
		$clean = array();
		foreach ( $value as $key => $item ) {
			$cleanKey = is_string( $key ) ? sanitize_key( $key ) : $key;
			if ( is_array( $item ) ) {
				$clean[ $cleanKey ] = $this->sanitizeAttributes( $item );
			} elseif ( is_bool( $item ) || is_int( $item ) || is_float( $item ) ) {
				$clean[ $cleanKey ] = $item;
			} else {
				$clean[ $cleanKey ] = sanitize_text_field( (string) $item );
			}
		}
		return $clean;
	}
}
