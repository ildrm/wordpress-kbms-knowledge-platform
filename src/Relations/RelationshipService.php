<?php

declare(strict_types=1);

namespace KBMS\Relations;

use DomainException;
use KBMS\Permissions\AuthorizationInterface;

final class RelationshipService {

	public function __construct(
		private readonly RelationshipRepository $repository,
		private readonly AuthorizationInterface $authorization
	) {
	}

	public function add( int $sourceId, int $targetId, string $type, int $userId ): void {
		if ( $sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId || ! RelationshipTypes::valid( $type ) ) {
			throw new DomainException( __( 'Invalid relationship.', 'wp-kbms' ) );
		}
		if ( ! $this->authorization->can( 'edit', $sourceId, $userId ) || ! $this->authorization->can( 'view', $targetId, $userId ) ) {
			throw new DomainException( __( 'You cannot relate these knowledge items.', 'wp-kbms' ) );
		}
		if ( get_post_type( $sourceId ) !== 'kbms_item' || get_post_type( $targetId ) !== 'kbms_item' ) {
			throw new DomainException( __( 'Relationships require knowledge items.', 'wp-kbms' ) );
		}
		if ( RelationshipTypes::isAcyclic( $type ) && $this->repository->pathExists( $targetId, $sourceId, $type ) ) {
			throw new DomainException( __( 'This relationship would create a cycle.', 'wp-kbms' ) );
		}
		if ( ! $this->repository->add( $sourceId, $targetId, $type, $userId ) ) {
			throw new DomainException( __( 'The relationship could not be saved.', 'wp-kbms' ) );
		}
	}

	/** @return list<array{id: int, source_id: int, target_id: int, relation_type: string}> */
	public function visibleNeighbors( int $itemId, int $userId, int $limit = 50 ): array {
		if ( ! $this->authorization->can( 'view', $itemId, $userId ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$this->repository->neighbors( $itemId, $limit ),
				fn ( array $row ): bool => $this->authorization->can(
					'view',
					$row['source_id'] === $itemId ? $row['target_id'] : $row['source_id'],
					$userId
				)
			)
		);
	}
}
