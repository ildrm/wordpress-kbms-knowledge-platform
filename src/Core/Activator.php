<?php

declare(strict_types=1);

namespace KBMS\Core;

use KBMS\Database\Migrator;
use KBMS\Knowledge\KnowledgePostType;
use KBMS\Permissions\CapabilityRegistrar;

final class Activator {

	public static function activate( bool $networkWide = false ): void {
		if ( is_multisite() && $networkWide ) {
			$siteIds = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $siteIds as $siteId ) {
				switch_to_blog( (int) $siteId );
				self::activateSite();
				restore_current_blog();
			}
			return;
		}

		self::activateSite();
	}

	private static function activateSite(): void {
		global $wpdb;

		( new Migrator( $wpdb ) )->migrate();
		( new CapabilityRegistrar() )->installCapabilities();
		( new KnowledgePostType() )->register();
		flush_rewrite_rules( false );
	}
}
