<?php

declare(strict_types=1);

namespace KBMS\Search;

final class SearchResult {

	/** @var SearchHit[] */
	private $hits;
	private $total;
	private $page;
	private $perPage;
	private $scopeFingerprint;

	/** @param SearchHit[] $hits */
	public function __construct( array $hits, int $total, int $page, int $perPage, string $scopeFingerprint ) {
		foreach ( $hits as $hit ) {
			if ( ! $hit instanceof SearchHit ) {
				throw new \InvalidArgumentException( 'Every search result must be a SearchHit.' );
			}
		}
		if ( '' === trim( $scopeFingerprint ) ) {
			throw new \InvalidArgumentException( 'Search results require an authorization scope fingerprint.' );
		}

		$this->hits             = array_values( $hits );
		$this->total            = max( 0, $total );
		$this->page             = max( 1, $page );
		$this->perPage          = max( 1, $perPage );
		$this->scopeFingerprint = $scopeFingerprint;
	}

	/** @return SearchHit[] */
	public function hits(): array {
		return $this->hits; }
	public function total(): int {
		return $this->total; }
	public function page(): int {
		return $this->page; }
	public function perPage(): int {
		return $this->perPage; }
	public function scopeFingerprint(): string {
		return $this->scopeFingerprint; }

	/** @return array<string, mixed> */
	public function toArray(): array {
		return array(
			'items'    => array_map(
				static function ( SearchHit $hit ): array {
					return $hit->toArray(); },
				$this->hits
			),
			'total'    => $this->total,
			'page'     => $this->page,
			'per_page' => $this->perPage,
		);
	}
}
