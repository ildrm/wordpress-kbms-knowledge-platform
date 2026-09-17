<?php

declare(strict_types=1);

namespace KBMS\Core;

final class Deactivator {

	public static function deactivate(): void {
		// Data and roles are intentionally retained. Uninstall requires explicit opt-in.
		wp_clear_scheduled_hook( 'kbms_daily_maintenance' );
		flush_rewrite_rules( false );
	}
}
