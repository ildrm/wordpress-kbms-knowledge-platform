<?php

declare(strict_types=1);

namespace KBMS\Health;

final class HealthCheckResult {

	public const GOOD        = 'good';
	public const RECOMMENDED = 'recommended';
	public const CRITICAL    = 'critical';

	private $status;
	private $label;
	private $description;

	public function __construct( string $status, string $label, string $description ) {
		if ( ! in_array( $status, array( self::GOOD, self::RECOMMENDED, self::CRITICAL ), true ) ) {
			throw new \InvalidArgumentException( 'Unknown health status.' );
		}
		$this->status      = $status;
		$this->label       = $label;
		$this->description = $description;
	}

	public function status(): string {
		return $this->status; }

	/** @return array<string, string> */
	public function toArray(): array {
		return array(
			'status'      => $this->status,
			'label'       => $this->label,
			'description' => $this->description,
		);
	}
}
