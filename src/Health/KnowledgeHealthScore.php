<?php

declare(strict_types=1);

namespace KBMS\Health;

final class KnowledgeHealthScore {

	private $score;
	/** @var array<string, float> */
	private $factors;

	/** @param array<string, float> $factors */
	public function __construct( float $score, array $factors ) {
		$this->score   = max( 0.0, min( 100.0, $score ) );
		$this->factors = $factors;
	}

	public function score(): float {
		return $this->score; }

	/** @return array<string, mixed> */
	public function toArray(): array {
		return array(
			'score'   => $this->score,
			'factors' => $this->factors,
		);
	}
}
