<?php

declare(strict_types=1);

namespace KBMS\BackgroundJobs;

use KBMS\Core\Hookable;

final class Scheduler implements Hookable {

	public const DAILY_HOOK = 'kbms_daily_maintenance';

	public function registerHooks(): void {
		add_action( 'init', array( $this, 'ensureScheduled' ) );
		add_action( self::DAILY_HOOK, array( $this, 'runDaily' ) );
	}

	public function ensureScheduled(): void {
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
	}

	public function runDaily(): void {
		$lock = 'kbms_daily_maintenance_lock';
		if ( get_transient( $lock ) !== false ) {
			return;
		}
		set_transient( $lock, 1, 15 * MINUTE_IN_SECONDS );
		try {
			do_action( 'kbms/run_review_notifications' );
			do_action( 'kbms/prune_retained_data' );
			do_action( 'kbms/check_broken_links_batch' );
		} finally {
			delete_transient( $lock );
		}
	}
}
