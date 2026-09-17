<?php

declare(strict_types=1);

namespace KBMS\Analytics;

use KBMS\Admin\Settings;
use KBMS\Core\Hookable;
use KBMS\Permissions\AuthorizationInterface;
use KBMS\Search\SearchResult;
use wpdb;

final class Recorder implements Hookable {

	public function __construct( private readonly wpdb $db, private readonly AuthorizationInterface $authorization ) {
	}

	public function registerHooks(): void {
		if ( ! Settings::get( 'analytics_enabled' ) ) {
			return;
		}
		add_action( 'wp', array( $this, 'recordView' ) );
		add_action( 'kbms_search_completed', array( $this, 'recordSearch' ), 10, 2 );
	}

	public function recordView(): void {
		if ( ! is_singular( 'kbms_item' ) ) {
			return;
		}
		$postId = get_queried_object_id();
		if ( $this->authorization->can( 'read', $postId, get_current_user_id() ) ) {
			$this->increment( 'view', 'knowledge', $postId, '' );
		}
	}

	public function recordSearch( string $term, SearchResult $result ): void {
		$normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $term ) ) : strtolower( trim( $term ) );
		$queryHash  = hash_hmac( 'sha256', $normalized, wp_salt( 'auth' ) );
		$this->increment( $result->total() === 0 ? 'failed_search' : 'search', 'query', 0, $queryHash );
	}

	private function increment( string $metric, string $objectType, int $objectId, string $dimensionHash ): void {
		$table         = $this->db->prefix . 'kbms_analytics_daily';
		$dimensionHash = $dimensionHash !== '' ? $dimensionHash : hash( 'sha256', 'none' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses the trusted WordPress prefix.
		$sql = $this->db->prepare(
			"INSERT INTO {$table} (metric_date,metric,object_type,object_id,dimension_hash,dimensions,metric_value)
			 VALUES (%s,%s,%s,%d,%s,NULL,1)
			 ON DUPLICATE KEY UPDATE metric_value = metric_value + 1",
			gmdate( 'Y-m-d' ),
			$metric,
			$objectType,
			$objectId,
			$dimensionHash
		);
		$this->db->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
	}
}
