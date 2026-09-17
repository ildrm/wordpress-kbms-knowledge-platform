<?php

declare(strict_types=1);

namespace KBMS\Search;

final class SearchHit {

	private $id;
	private $title;
	private $excerpt;
	private $url;
	private $score;
	/** @var array<string, mixed> */
	private $metadata;

	/** @param array<string, mixed> $metadata */
	public function __construct( int $id, string $title, string $excerpt, string $url, float $score, array $metadata = array() ) {
		$this->id       = $id;
		$this->title    = $title;
		$this->excerpt  = $excerpt;
		$this->url      = $this->safeUrl( $url );
		$this->score    = max( 0.0, min( 1.0, $score ) );
		$this->metadata = $metadata;
	}

	public function id(): int {
		return $this->id; }
	public function title(): string {
		return $this->title; }
	public function excerpt(): string {
		return $this->excerpt; }
	public function url(): string {
		return $this->url; }
	public function score(): float {
		return $this->score; }
	/** @return array<string, mixed> */
	public function metadata(): array {
		return $this->metadata; }

	/** @return array<string, mixed> */
	public function toArray(): array {
		return array(
			'id'       => $this->id,
			'title'    => $this->title,
			'excerpt'  => $this->excerpt,
			'url'      => $this->url,
			'score'    => $this->score,
			'metadata' => $this->metadata,
		);
	}

	private function safeUrl( string $url ): string {
		$url = trim( $url );
		if ( '' === $url || ( '/' === $url[0] && ! str_starts_with( $url, '//' ) ) ) {
			return $url;
		}
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}
}
