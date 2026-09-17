<?php

declare(strict_types=1);

namespace KBMS\Relations;

use wpdb;

final class RelationshipRepository {

	public function __construct( private readonly wpdb $db ) {
	}

	public function add( int $sourceId, int $targetId, string $type, int $userId ): bool {
		$result = $this->db->query(
			$this->db->prepare(
				"INSERT IGNORE INTO {$this->db->prefix}kbms_relationships (source_id, target_id, relation_type, created_by, created_at) VALUES (%d, %d, %s, %d, %s)",
				$sourceId,
				$targetId,
				$type,
				$userId,
				current_time( 'mysql', true )
			)
		);
		return $result !== false;
	}

	public function remove( int $sourceId, int $targetId, string $type ): bool {
		return $this->db->delete(
			$this->db->prefix . 'kbms_relationships',
			array(
				'source_id'     => $sourceId,
				'target_id'     => $targetId,
				'relation_type' => $type,
			),
			array( '%d', '%d', '%s' )
		) !== false;
	}

	/** @return list<array{id: int, source_id: int, target_id: int, relation_type: string}> */
	public function neighbors( int $itemId, int $limit = 50 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, source_id, target_id, relation_type FROM {$this->db->prefix}kbms_relationships WHERE source_id = %d OR target_id = %d ORDER BY id DESC LIMIT %d",
				$itemId,
				$itemId,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static fn ( array $row ): array => array(
				'id'            => (int) $row['id'],
				'source_id'     => (int) $row['source_id'],
				'target_id'     => (int) $row['target_id'],
				'relation_type' => (string) $row['relation_type'],
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	public function pathExists( int $from, int $to, string $type, int $maxDepth = 100 ): bool {
		$frontier = array( $from );
		$visited  = array();
		for ( $depth = 0; $depth < $maxDepth && $frontier !== array(); ++$depth ) {
			$node = array_shift( $frontier );
			if ( $node === $to ) {
				return true;
			}
			if ( isset( $visited[ $node ] ) ) {
				continue;
			}
			$visited[ $node ] = true;
			$next             = $this->db->get_col(
				$this->db->prepare(
					"SELECT target_id FROM {$this->db->prefix}kbms_relationships WHERE source_id = %d AND relation_type = %s LIMIT 101",
					$node,
					$type
				)
			);
			foreach ( $next as $id ) {
				$id = (int) $id;
				if ( ! isset( $visited[ $id ] ) ) {
					$frontier[] = $id;
				}
			}
		}
		return false;
	}
}
