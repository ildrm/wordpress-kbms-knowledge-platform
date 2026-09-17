<?php

declare(strict_types=1);

namespace KBMS\Permissions;

use KBMS\Core\Hookable;

final class CapabilityRegistrar implements Hookable {

	private const VERSION = '2';

	/** @var list<string> */
	public const ALL_CAPABILITIES = array(
		'read_kbms_item',
		'read_private_kbms_items',
		'edit_kbms_item',
		'edit_kbms_items',
		'edit_others_kbms_items',
		'edit_published_kbms_items',
		'publish_kbms_items',
		'delete_kbms_item',
		'delete_kbms_items',
		'delete_others_kbms_items',
		'delete_published_kbms_items',
		'manage_kbms',
		'manage_kbms_spaces',
		'manage_kbms_workflows',
		'review_kbms_items',
		'verify_kbms_items',
		'export_kbms_items',
		'use_kbms_ai',
		'view_kbms_audit_log',
	);

	public function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'ensureCapabilities' ) );
	}

	public function ensureCapabilities(): void {
		if ( get_option( 'kbms_capability_version' ) !== self::VERSION ) {
			$this->installCapabilities();
		}
	}

	public function installCapabilities(): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator !== null ) {
			foreach ( self::ALL_CAPABILITIES as $capability ) {
				$administrator->add_cap( $capability );
			}
		}

		$contributor = array(
			'read'              => true,
			'upload_files'      => true,
			'read_kbms_item'    => true,
			'edit_kbms_item'    => true,
			'edit_kbms_items'   => true,
			'delete_kbms_item'  => true,
			'delete_kbms_items' => true,
			'use_kbms_ai'       => true,
		);
		$reviewer    = array(
			'read'                    => true,
			'read_kbms_item'          => true,
			'read_private_kbms_items' => true,
			'edit_kbms_item'          => true,
			'edit_kbms_items'         => true,
			'edit_others_kbms_items'  => true,
			'review_kbms_items'       => true,
			'verify_kbms_items'       => true,
			'export_kbms_items'       => true,
			'use_kbms_ai'             => true,
		);
		$manager     = array_fill_keys( self::ALL_CAPABILITIES, true ) + array(
			'read'         => true,
			'upload_files' => true,
		);

		$this->upsertRole( 'kbms_contributor', __( 'Knowledge Contributor', 'wp-kbms' ), $contributor );
		$this->upsertRole( 'kbms_reviewer', __( 'Knowledge Reviewer', 'wp-kbms' ), $reviewer );
		$this->upsertRole( 'kbms_manager', __( 'Knowledge Manager', 'wp-kbms' ), $manager );

		update_option( 'kbms_capability_version', self::VERSION, false );
	}

	/** @param array<string,bool> $capabilities */
	private function upsertRole( string $slug, string $name, array $capabilities ): void {
		add_role( $slug, $name, $capabilities );
		$role = get_role( $slug );
		if ( $role === null ) {
			return;
		}
		foreach ( $capabilities as $capability => $grant ) {
			$role->add_cap( $capability, $grant );
		}
	}
}
