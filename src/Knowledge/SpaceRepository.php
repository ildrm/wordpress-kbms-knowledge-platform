<?php

declare(strict_types=1);

namespace KBMS\Knowledge;

use DomainException;
use Throwable;
use wpdb;

final class SpaceRepository {

	public const MEMBER_ROLES = array( 'viewer', 'contributor', 'reviewer', 'admin' );

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	public function find( int $id ): ?Space {
		$table = $this->db->prefix . 'kbms_spaces';
		$row   = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function findBySlug( string $slug ): ?Space {
		$table = $this->db->prefix . 'kbms_spaces';
		$slug  = sanitize_title( $slug );
		$row   = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/** @return list<Space> */
	public function all( int $limit = 100, int $offset = 0 ): array {
		$table  = $this->db->prefix . 'kbms_spaces';
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the trusted WordPress prefix.
		$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$table} ORDER BY name ASC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		return array_map( fn ( array $row ): Space => $this->hydrate( $row ), is_array( $rows ) ? $rows : array() );
	}

	/** @param array<string,mixed> $settings */
	public function create( string $name, string $slug, string $description, string $visibility, int $ownerId, array $settings = array() ): Space {
		$name        = sanitize_text_field( $name );
		$slug        = sanitize_title( $slug !== '' ? $slug : $name );
		$description = sanitize_textarea_field( $description );
		$this->assertValid( $name, $slug, $visibility, $ownerId );

		$table    = $this->db->prefix . 'kbms_spaces';
		$now      = current_time( 'mysql', true );
		$inserted = $this->db->insert(
			$table,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'visibility'  => $visibility,
				'owner_id'    => $ownerId,
				'settings'    => wp_json_encode( $settings ),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( $inserted !== 1 ) {
			throw new DomainException( __( 'Unable to create the knowledge space.', 'wp-kbms' ) );
		}

		$space = new Space( (int) $this->db->insert_id, $name, $slug, $description, $visibility, $ownerId, $settings );
		try {
			$this->setMemberRole( $space->id, $ownerId, 'admin' );
		} catch ( Throwable $error ) {
			// Creating a space without its owner membership would make it unmanageable.
			$this->db->delete( $table, array( 'id' => $space->id ), array( '%d' ) );
			throw $error;
		}
		do_action( 'kbms_space_created', $space );
		return $space;
	}

	/** @param array<string,mixed> $changes */
	public function update( int $id, array $changes ): Space {
		$existing = $this->find( $id );
		if ( $existing === null ) {
			throw new DomainException( __( 'Knowledge space not found.', 'wp-kbms' ) );
		}

		$allowed     = array_intersect_key( $changes, array_flip( array( 'name', 'slug', 'description', 'visibility', 'owner_id', 'settings' ) ) );
		$name        = isset( $allowed['name'] ) ? sanitize_text_field( (string) $allowed['name'] ) : $existing->name;
		$slug        = isset( $allowed['slug'] ) ? sanitize_title( (string) $allowed['slug'] ) : $existing->slug;
		$description = isset( $allowed['description'] ) ? sanitize_textarea_field( (string) $allowed['description'] ) : $existing->description;
		$visibility  = isset( $allowed['visibility'] ) ? (string) $allowed['visibility'] : $existing->visibility;
		$ownerId     = isset( $allowed['owner_id'] ) ? absint( $allowed['owner_id'] ) : $existing->ownerId;
		$settings    = isset( $allowed['settings'] ) && is_array( $allowed['settings'] ) ? $allowed['settings'] : $existing->settings;
		$this->assertValid( $name, $slug, $visibility, $ownerId );

		$table   = $this->db->prefix . 'kbms_spaces';
		$updated = $this->db->update(
			$table,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'visibility'  => $visibility,
				'owner_id'    => $ownerId,
				'settings'    => wp_json_encode( $settings ),
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
		if ( $updated === false ) {
			throw new DomainException( __( 'Unable to update the knowledge space.', 'wp-kbms' ) );
		}

		$space = new Space( $id, $name, $slug, $description, $visibility, $ownerId, $settings );
		$this->setMemberRole( $id, $ownerId, 'admin' );
		do_action( 'kbms_space_updated', $space, $existing );
		return $space;
	}

	public function setMemberRole( int $spaceId, int $userId, string $role ): void {
		if ( $spaceId <= 0 || $userId <= 0 || ! in_array( $role, self::MEMBER_ROLES, true ) ) {
			throw new DomainException( __( 'Invalid space membership.', 'wp-kbms' ) );
		}
		if ( $this->find( $spaceId ) === null || get_userdata( $userId ) === false ) {
			throw new DomainException( __( 'The space or user does not exist.', 'wp-kbms' ) );
		}

		$table = $this->db->prefix . 'kbms_space_members';
		$sql   = $this->db->prepare(
			"INSERT INTO {$table} (space_id,user_id,member_role,created_at)
             VALUES (%d,%d,%s,%s)
             ON DUPLICATE KEY UPDATE member_role = VALUES(member_role)",
			$spaceId,
			$userId,
			$role,
			current_time( 'mysql', true )
		);
		if ( $this->db->query( $sql ) === false ) {
			throw new DomainException( __( 'Unable to update space membership.', 'wp-kbms' ) );
		}
		do_action( 'kbms_space_membership_changed', $spaceId, $userId, $role );
	}

	public function removeMember( int $spaceId, int $userId ): void {
		$space = $this->find( $spaceId );
		if ( $space !== null && $space->ownerId === $userId ) {
			throw new DomainException( __( 'The space owner cannot be removed.', 'wp-kbms' ) );
		}
		$table = $this->db->prefix . 'kbms_space_members';
		if ( $this->db->delete(
			$table,
			array(
				'space_id' => $spaceId,
				'user_id'  => $userId,
			),
			array( '%d', '%d' )
		) === false ) {
			throw new DomainException( __( 'Unable to remove space membership.', 'wp-kbms' ) );
		}
		do_action( 'kbms_space_membership_removed', $spaceId, $userId );
	}

	/** @param array<string,mixed> $row */
	private function hydrate( array $row ): Space {
		$settings = json_decode( (string) ( $row['settings'] ?? '' ), true );
		return new Space(
			(int) $row['id'],
			(string) $row['name'],
			(string) $row['slug'],
			(string) $row['description'],
			(string) $row['visibility'],
			(int) $row['owner_id'],
			is_array( $settings ) ? $settings : array()
		);
	}

	private function assertValid( string $name, string $slug, string $visibility, int $ownerId ): void {
		if ( $name === '' || $slug === '' || $ownerId <= 0 || get_userdata( $ownerId ) === false
			|| ! in_array( $visibility, Space::VISIBILITIES, true ) ) {
			throw new DomainException( __( 'Invalid knowledge space data.', 'wp-kbms' ) );
		}
	}
}
