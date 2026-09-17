<?php

declare(strict_types=1);

namespace KBMS\AI;

final class Citation {

	private $resourceId;
	private $title;
	private $url;
	private $heading;
	private $version;

	public function __construct( int $resourceId, string $title, string $url, string $heading = '', string $version = '' ) {
		$this->resourceId = $resourceId;
		$this->title      = $title;
		$this->url        = $this->safeUrl( $url );
		$this->heading    = $heading;
		$this->version    = $version;
	}

	/** @return array<string, mixed> */
	public function toArray(): array {
		return array(
			'resource_id' => $this->resourceId,
			'title'       => $this->title,
			'url'         => $this->url,
			'heading'     => $this->heading,
			'version'     => $this->version,
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
