<?php

declare(strict_types=1);

namespace KBMS\Health;

final class CallbackHealthCheck implements HealthCheckInterface {

	private $id;
	/** @var callable */
	private $callback;

	public function __construct( string $id, callable $callback ) {
		if ( ! preg_match( '/^[a-z0-9_]+$/', $id ) ) {
			throw new \InvalidArgumentException( 'Health check IDs may contain lowercase letters, digits, and underscores only.' );
		}
		$this->id       = $id;
		$this->callback = $callback;
	}

	public function id(): string {
		return $this->id; }

	public function run(): HealthCheckResult {
		$result = call_user_func( $this->callback );
		if ( ! $result instanceof HealthCheckResult ) {
			throw new \UnexpectedValueException( 'A health check must return HealthCheckResult.' );
		}
		return $result;
	}
}
