<?php

declare(strict_types=1);

namespace KBMS\Search;

final class SearchQuery {

	/** @var string */
	private $term;

	/** @var int */
	private $userId;

	/** @var int */
	private $page;

	/** @var int */
	private $perPage;

	/** @var array<string, mixed> */
	private $filters;

	/** @param array<string, mixed> $filters */
	public function __construct( string $term, int $userId, int $page = 1, int $perPage = 10, array $filters = array() ) {
		$term = trim( $term );
		if ( '' === $term ) {
			throw new \InvalidArgumentException( 'Search term must not be empty.' );
		}

		$this->term    = $term;
		$this->userId  = max( 0, $userId );
		$this->page    = max( 1, $page );
		$this->perPage = min( 50, max( 1, $perPage ) );
		$this->filters = $filters;
	}

	public function term(): string {
		return $this->term; }
	public function userId(): int {
		return $this->userId; }
	public function page(): int {
		return $this->page; }
	public function perPage(): int {
		return $this->perPage; }

	/** @return array<string, mixed> */
	public function filters(): array {
		return $this->filters; }
}
