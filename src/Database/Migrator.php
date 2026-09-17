<?php

declare(strict_types=1);

namespace KBMS\Database;

use KBMS\Audit\AuditLogger;
use KBMS\Core\Hookable;
use RuntimeException;
use Throwable;
use wpdb;

final class Migrator implements Hookable {

	public const OPTION = 'kbms_schema_version';

	private wpdb $db;
	private ?AuditLogger $audit;

	/** @var list<Migration> */
	private array $migrations;

	public function __construct( wpdb $db, ?AuditLogger $audit = null, ?array $migrations = null ) {
		$this->db         = $db;
		$this->audit      = $audit;
		$this->migrations = $migrations ?? array( new InitialSchema( $db ) );
	}

	public function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'migrate' ) );
		add_action( 'wp_initialize_site', array( $this, 'initializeSite' ), 10, 1 );
	}

	/** @param \WP_Site $site */
	public function initializeSite( $site ): void {
		switch_to_blog( (int) $site->blog_id );
		try {
			$this->migrate();
		} finally {
			restore_current_blog();
		}
	}

	public function migrate(): void {
		$installed = (int) get_option( self::OPTION, 0 );
		usort( $this->migrations, static fn ( Migration $a, Migration $b ): int => $a->version() <=> $b->version() );

		foreach ( $this->migrations as $migration ) {
			if ( $migration->version() <= $installed ) {
				continue;
			}

			try {
				$migration->up();
				if ( $this->db->last_error !== '' ) {
					throw new RuntimeException( $this->db->last_error );
				}
				update_option( self::OPTION, $migration->version(), false );
				$installed = $migration->version();
				$this->audit?->record( 'schema.migrated', 'schema', (string) $installed );
			} catch ( Throwable $error ) {
				$this->audit?->record(
					'schema.failed',
					'schema',
					(string) $migration->version(),
					array(
						'error_class' => get_class( $error ),
						'message'     => $error->getMessage(),
					)
				);
				throw $error;
			}
		}
	}
}
