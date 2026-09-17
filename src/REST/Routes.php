<?php

declare(strict_types=1);

namespace KBMS\REST;

use KBMS\Core\Hookable;

final class Routes implements Hookable {

	/** @var array<int, object> */
	private $controllers;

	/** @param array<int, object> $controllers */
	public function __construct( array $controllers ) {
		$this->controllers = $controllers;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerHooks(): void {
		$this->register();
	}

	public function registerRoutes(): void {
		foreach ( $this->controllers as $controller ) {
			if ( ! method_exists( $controller, 'register' ) ) {
				throw new \InvalidArgumentException( 'Every REST controller must expose register().' );
			}
			$controller->register();
		}
	}
}
