<?php

declare(strict_types=1);

namespace KBMS\Database;

use RuntimeException;
use wpdb;

final class InitialSchema implements Migration {

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	public function version(): int {
		return 1;
	}

	public function up(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->db->get_charset_collate();
		$prefix  = $this->db->prefix . 'kbms_';

		$queries = array(
			"CREATE TABLE {$prefix}spaces (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(191) NOT NULL,
                slug varchar(191) NOT NULL,
                description longtext NOT NULL,
                visibility varchar(20) NOT NULL DEFAULT 'private',
                owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
                settings longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY slug (slug),
                KEY visibility (visibility),
                KEY owner_id (owner_id)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}space_members (
                space_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                member_role varchar(20) NOT NULL DEFAULT 'viewer',
                created_at datetime NOT NULL,
                PRIMARY KEY  (space_id,user_id),
                KEY user_id (user_id),
                KEY role_space (member_role,space_id)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}knowledge_meta (
                post_id bigint(20) unsigned NOT NULL,
                space_id bigint(20) unsigned NOT NULL,
                owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
                reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0,
                workflow_id bigint(20) unsigned NULL,
                workflow_state varchar(64) NOT NULL DEFAULT 'draft',
                verification_status varchar(32) NOT NULL DEFAULT 'unverified',
                confidentiality varchar(32) NOT NULL DEFAULT 'internal',
                risk_level varchar(20) NOT NULL DEFAULT 'low',
                language varchar(20) NOT NULL DEFAULT '',
                review_at datetime NULL,
                expires_at datetime NULL,
                verified_at datetime NULL,
                version bigint(20) unsigned NOT NULL DEFAULT 1,
                attributes longtext NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (post_id),
                KEY space_status (space_id,workflow_state),
                KEY owner_id (owner_id),
                KEY reviewer_id (reviewer_id),
                KEY review_at (review_at),
                KEY expires_at (expires_at),
                KEY verification (verification_status)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}workflows (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                space_id bigint(20) unsigned NULL,
                name varchar(191) NOT NULL,
                initial_state varchar(64) NOT NULL,
                states longtext NOT NULL,
                transitions longtext NOT NULL,
                enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY space_enabled (space_id,enabled)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}workflow_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                workflow_id bigint(20) unsigned NOT NULL,
                from_state varchar(64) NOT NULL,
                to_state varchar(64) NOT NULL,
                actor_id bigint(20) unsigned NOT NULL,
                comment text NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY post_created (post_id,created_at),
                KEY workflow_id (workflow_id)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}audit_log (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                occurred_at datetime NOT NULL,
                actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
                event_type varchar(100) NOT NULL,
                object_type varchar(64) NOT NULL,
                object_id varchar(191) NOT NULL,
                ip_hash char(64) NULL,
                correlation_id varchar(64) NULL,
                context longtext NULL,
                PRIMARY KEY  (id),
                KEY occurred_at (occurred_at),
                KEY actor_event (actor_id,event_type),
                KEY object_lookup (object_type,object_id),
                KEY correlation_id (correlation_id)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}relationships (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source_id bigint(20) unsigned NOT NULL,
                target_id bigint(20) unsigned NOT NULL,
                relation_type varchar(64) NOT NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY relationship (source_id,target_id,relation_type),
                KEY source_type (source_id,relation_type),
                KEY target_type (target_id,relation_type)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}feedback (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                rating tinyint unsigned NULL,
                feedback_type varchar(32) NOT NULL DEFAULT 'helpfulness',
                comment text NULL,
                status varchar(20) NOT NULL DEFAULT 'open',
                created_at datetime NOT NULL,
                resolved_at datetime NULL,
                PRIMARY KEY  (id),
                KEY post_created (post_id,created_at),
                KEY status_created (status,created_at),
                KEY user_id (user_id)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}analytics_daily (
                metric_date date NOT NULL,
                metric varchar(64) NOT NULL,
                object_type varchar(32) NOT NULL DEFAULT '',
                object_id bigint(20) unsigned NOT NULL DEFAULT 0,
                dimension_hash char(64) NOT NULL,
                dimensions longtext NULL,
                metric_value bigint(20) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY  (metric_date,metric,object_type,object_id,dimension_hash),
                KEY metric_date_lookup (metric,metric_date),
                KEY object_lookup (object_type,object_id,metric_date)
            ) ENGINE=InnoDB {$charset};",
			"CREATE TABLE {$prefix}jobs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_type varchar(64) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                payload longtext NULL,
                attempts smallint unsigned NOT NULL DEFAULT 0,
                max_attempts smallint unsigned NOT NULL DEFAULT 3,
                available_at datetime NOT NULL,
                locked_at datetime NULL,
                locked_by varchar(64) NULL,
                last_error text NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY queue_claim (status,available_at,locked_at),
                KEY job_type_status (job_type,status)
            ) ENGINE=InnoDB {$charset};",
		);

		foreach ( $queries as $query ) {
			$this->db->last_error = '';
			dbDelta( $query );
			if ( $this->db->last_error !== '' ) {
				throw new RuntimeException( $this->db->last_error );
			}
		}
	}
}
