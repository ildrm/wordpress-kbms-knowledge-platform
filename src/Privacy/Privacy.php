<?php

declare(strict_types=1);

namespace KBMS\Privacy;

use KBMS\Core\Hookable;
use wpdb;

final class Privacy implements Hookable {

	public function __construct( private readonly wpdb $db ) {
	}

	public function registerHooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'kbms/prune_retained_data', array( $this, 'prune' ) );
	}

	/** @param array<string, mixed> $exporters @return array<string, mixed> */
	public function exporters( array $exporters ): array {
		$exporters['kbms-feedback'] = array(
			'exporter_friendly_name' => __( 'KBMS feedback', 'wp-kbms' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/** @param array<string, mixed> $erasers @return array<string, mixed> */
	public function erasers( array $erasers ): array {
		$erasers['kbms-feedback'] = array(
			'eraser_friendly_name' => __( 'KBMS feedback', 'wp-kbms' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/** @return array{data: list<array<string, mixed>>, done: bool} */
	public function export( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$offset = max( 0, ( $page - 1 ) * 100 );
		$rows   = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, post_id, rating, comment, created_at FROM {$this->db->prefix}kbms_feedback WHERE user_id = %d ORDER BY id LIMIT 100 OFFSET %d",
				$user->ID,
				$offset
			),
			ARRAY_A
		);
		$data   = array_map(
			static fn ( array $row ): array => array(
				'group_id'    => 'kbms-feedback',
				'group_label' => __( 'Knowledge feedback', 'wp-kbms' ),
				'item_id'     => 'kbms-feedback-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Knowledge item', 'wp-kbms' ),
						'value' => (string) (int) $row['post_id'],
					),
					array(
						'name'  => __( 'Helpful', 'wp-kbms' ),
						'value' => (int) $row['rating'] === 1 ? __( 'Yes', 'wp-kbms' ) : __( 'No', 'wp-kbms' ),
					),
					array(
						'name'  => __( 'Comment', 'wp-kbms' ),
						'value' => (string) $row['comment'],
					),
					array(
						'name'  => __( 'Date', 'wp-kbms' ),
						'value' => (string) $row['created_at'],
					),
				),
			),
			is_array( $rows ) ? $rows : array()
		);
		return array(
			'data' => $data,
			'done' => count( $data ) < 100,
		);
	}

	/** @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool} */
	public function erase( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}
		$updated = $this->db->update( $this->db->prefix . 'kbms_feedback', array( 'user_id' => 0 ), array( 'user_id' => $user->ID ), array( '%d' ), array( '%d' ) );
		return array(
			'items_removed'  => $updated !== false && $updated > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	public function prune(): void {
		$settings = (array) get_option( 'kbms_settings', array() );
		$days     = max( 30, min( 3650, absint( $settings['retention_days'] ?? 365 ) ) );
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$this->db->query(
			$this->db->prepare(
				"DELETE FROM {$this->db->prefix}kbms_feedback WHERE created_at < %s LIMIT 1000",
				$cutoff
			)
		);
	}
}
