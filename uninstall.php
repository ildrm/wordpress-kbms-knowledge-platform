<?php
/**
 * KBMS uninstall handler. Data is retained unless an administrator explicitly
 * enabled deletion before uninstalling the plugin.
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cleanup = static function (): void {
	if ( (bool) get_option( 'kbms_delete_data_on_uninstall', false ) !== true ) {
		return;
	}

	global $wpdb;
	$tables = array(
		'kbms_spaces',
		'kbms_space_members',
		'kbms_knowledge_meta',
		'kbms_workflows',
		'kbms_relationships',
		'kbms_workflow_events',
		'kbms_audit_log',
		'kbms_feedback',
		'kbms_analytics_daily',
		'kbms_jobs',
	);

	foreach ( $tables as $table ) {
		$name = $wpdb->prefix . $table;
		// Table names are drawn only from the fixed allowlist above.
		$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	do {
		$postIds = get_posts(
			array(
				'post_type'      => 'kbms_item',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
			)
		);
		$deleted = 0;
		$postCount = count( $postIds );
		foreach ( $postIds as $postId ) {
			if ( wp_delete_post( (int) $postId, true ) !== false ) {
				++$deleted;
			}
		}
	} while ( $postCount === 100 && $deleted > 0 );

	$capabilities = array(
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
	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		foreach ( $capabilities as $capability ) {
			$administrator->remove_cap( $capability );
		}
	}
	remove_role( 'kbms_contributor' );
	remove_role( 'kbms_reviewer' );
	remove_role( 'kbms_manager' );

	delete_option( 'kbms_schema_version' );
	delete_option( 'kbms_capability_version' );
	delete_option( 'kbms_settings' );
	delete_option( 'kbms_variables' );
	delete_option( 'kbms_delete_data_on_uninstall' );
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $siteId ) {
		switch_to_blog( (int) $siteId );
		$cleanup();
		restore_current_blog();
	}
} else {
	$cleanup();
}
