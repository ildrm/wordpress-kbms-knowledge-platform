<?php

declare(strict_types=1);

namespace KBMS\CLI;

use KBMS\Core\Hookable;
use WP_CLI;

final class Commands implements Hookable {

	public function registerHooks(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'kbms status', array( $this, 'status' ) );
			WP_CLI::add_command( 'kbms review-due', array( $this, 'reviewDue' ) );
		}
	}

	/** @param list<string> $args @param array<string, mixed> $assocArgs */
	public function status( array $args, array $assocArgs ): void {
		$counts = wp_count_posts( 'kbms_item' );
		WP_CLI::line( 'KBMS ' . KBMS_VERSION );
		WP_CLI::line( 'Schema: ' . (string) get_option( 'kbms_schema_version', '0' ) );
		WP_CLI::line( 'Published items: ' . (int) ( $counts->publish ?? 0 ) );
	}

	/** @param list<string> $args @param array<string, mixed> $assocArgs */
	public function reviewDue( array $args, array $assocArgs ): void {
		do_action( 'kbms/run_review_notifications' );
		WP_CLI::success( 'Review notification batch completed.' );
	}
}
