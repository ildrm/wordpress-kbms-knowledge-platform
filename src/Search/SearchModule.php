<?php

declare(strict_types=1);

namespace KBMS\Search;

use KBMS\Core\Hookable;

final class SearchModule implements Hookable {

	private $provider;

	public function __construct( SearchProviderInterface $provider ) {
		$this->provider = $provider;
	}

	public function register(): void {
		add_filter( 'kbms_search_provider', array( $this, 'provider' ) );
	}

	public function registerHooks(): void {
		$this->register();
	}

	/** @param mixed $current */
	public function provider( $current ): SearchProviderInterface {
		return $this->provider;
	}
}
