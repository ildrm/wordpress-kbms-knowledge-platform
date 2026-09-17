<?php

declare(strict_types=1);

namespace KBMS\AI;

final class VectorQuery {

	private $vector;
	private $userId;
	private $limit;
	/** @var array<string, mixed> */
	private $authorizationFilter;

	/** @param float[] $vector @param array<string, mixed> $authorizationFilter */
	public function __construct( array $vector, int $userId, int $limit, array $authorizationFilter ) {
		if ( ! $authorizationFilter ) {
			throw new \InvalidArgumentException( 'Vector retrieval requires a non-empty authorization filter.' );
		}
		$this->vector              = array_map( 'floatval', $vector );
		$this->userId              = max( 0, $userId );
		$this->limit               = min( 50, max( 1, $limit ) );
		$this->authorizationFilter = $authorizationFilter;
	}

	/** @return float[] */
	public function vector(): array {
		return $this->vector; }
	public function userId(): int {
		return $this->userId; }
	public function limit(): int {
		return $this->limit; }
	/** @return array<string, mixed> */
	public function authorizationFilter(): array {
		return $this->authorizationFilter; }
}
