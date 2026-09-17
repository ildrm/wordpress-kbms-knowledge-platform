<?php

declare(strict_types=1);

namespace KBMS\Health;

final class HealthStatus {

	/** @var HealthCheckInterface[] */
	private $checks;

	/** @param HealthCheckInterface[] $checks */
	public function __construct( array $checks ) {
		foreach ( $checks as $check ) {
			if ( ! $check instanceof HealthCheckInterface ) {
				throw new \InvalidArgumentException( 'Invalid health check.' );
			}
		}
		$this->checks = array_values( $checks );
	}

	/** @return array<string, mixed> */
	public function summary(): array {
		$overall = HealthCheckResult::GOOD;
		$results = array();
		foreach ( $this->checks as $check ) {
			try {
				$result = $check->run();
			} catch ( \Throwable $error ) {
				$result = new HealthCheckResult(
					HealthCheckResult::CRITICAL,
					'Health check failed',
					'A component health check could not be completed.'
				);
			}

			$results[] = array_merge( array( 'id' => $check->id() ), $result->toArray() );
			if ( HealthCheckResult::CRITICAL === $result->status() ) {
				$overall = HealthCheckResult::CRITICAL;
			} elseif ( HealthCheckResult::RECOMMENDED === $result->status() && HealthCheckResult::GOOD === $overall ) {
				$overall = HealthCheckResult::RECOMMENDED;
			}
		}
		return array(
			'status' => $overall,
			'checks' => $results,
		);
	}
}
