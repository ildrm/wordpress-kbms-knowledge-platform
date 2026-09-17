<?php

declare(strict_types=1);

namespace KBMS\Health;

use KBMS\Core\Hookable;

final class SiteHealth implements Hookable {

	public function registerHooks(): void {
		add_filter( 'site_status_tests', array( $this, 'tests' ) );
		add_filter( 'debug_information', array( $this, 'debugInformation' ) );
	}

	/** @param array<string, mixed> $tests @return array<string, mixed> */
	public function tests( array $tests ): array {
		$tests['direct']['kbms_schema'] = array(
			'label' => __( 'KBMS database schema', 'wp-kbms' ),
			'test'  => array( $this, 'schemaTest' ),
		);
		$tests['direct']['kbms_cron']   = array(
			'label' => __( 'KBMS maintenance schedule', 'wp-kbms' ),
			'test'  => array( $this, 'cronTest' ),
		);
		return $tests;
	}

	/** @return array<string, mixed> */
	public function schemaTest(): array {
		$current  = (string) get_option( 'kbms_schema_version', '0' );
		$expected = defined( 'KBMS_DB_VERSION' ) ? (string) KBMS_DB_VERSION : '1';
		$good     = version_compare( $current, $expected, '>=' );
		return array(
			'label'       => $good ? __( 'KBMS database is current', 'wp-kbms' ) : __( 'KBMS database requires migration', 'wp-kbms' ),
			'status'      => $good ? 'good' : 'critical',
			'badge'       => array(
				'label' => 'KBMS',
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $good ? __( 'The installed schema matches this plugin version.', 'wp-kbms' ) : __( 'Deactivate and reactivate KBMS, then inspect the audit log if migration still fails.', 'wp-kbms' ) ) . '</p>',
			'test'        => 'kbms_schema',
		);
	}

	/** @return array<string, mixed> */
	public function cronTest(): array {
		$scheduled = wp_next_scheduled( 'kbms_daily_maintenance' );
		return array(
			'label'       => $scheduled ? __( 'KBMS maintenance is scheduled', 'wp-kbms' ) : __( 'KBMS maintenance is not scheduled', 'wp-kbms' ),
			'status'      => $scheduled ? 'good' : 'recommended',
			'badge'       => array(
				'label' => 'KBMS',
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Review reminders, retention, and link checks depend on WP-Cron.', 'wp-kbms' ) . '</p>',
			'test'        => 'kbms_cron',
		);
	}

	/** @param array<string, mixed> $info @return array<string, mixed> */
	public function debugInformation( array $info ): array {
		$next         = wp_next_scheduled( 'kbms_daily_maintenance' );
		$info['kbms'] = array(
			'label'       => __( 'KBMS', 'wp-kbms' ),
			'description' => __( 'Operational information only; secrets and knowledge content are excluded.', 'wp-kbms' ),
			'fields'      => array(
				'version'   => array(
					'label' => __( 'Version', 'wp-kbms' ),
					'value' => KBMS_VERSION,
				),
				'schema'    => array(
					'label' => __( 'Schema', 'wp-kbms' ),
					'value' => (string) get_option( 'kbms_schema_version', '0' ),
				),
				'daily_job' => array(
					'label' => __( 'Next maintenance', 'wp-kbms' ),
					'value' => $next ? gmdate( DATE_ATOM, $next ) : __( 'Not scheduled', 'wp-kbms' ),
				),
			),
		);
		return $info;
	}
}
