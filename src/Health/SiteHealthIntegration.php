<?php

declare(strict_types=1);

namespace KBMS\Health;

use KBMS\Core\Hookable;

final class SiteHealthIntegration implements Hookable {

	private $health;

	public function __construct( HealthStatus $health ) {
		$this->health = $health;
	}

	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'addTests' ) );
	}

	public function registerHooks(): void {
		$this->register();
	}

	/** @param array<string, mixed> $tests @return array<string, mixed> */
	public function addTests( array $tests ): array {
		$tests['direct']['kbms_health'] = array(
			'label' => __( 'KBMS component health', 'wp-kbms' ),
			'test'  => array( $this, 'runTest' ),
		);
		return $tests;
	}

	/** @return array<string, mixed> */
	public function runTest(): array {
		$summary = $this->health->summary();
		$status  = (string) $summary['status'];
		$label   = HealthCheckResult::GOOD === $status
			? __( 'Knowledge management components are healthy', 'wp-kbms' )
			: __( 'Knowledge management components need attention', 'wp-kbms' );

		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Knowledge Management', 'wp-kbms' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Review the KBMS health endpoint for component details.', 'wp-kbms' ) . '</p>',
			'actions'     => '',
			'test'        => 'kbms_health',
		);
	}
}
