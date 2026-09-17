<?php

declare(strict_types=1);

namespace KBMS\AI;

final class GroundedAnswer {

	private $answer;
	/** @var Citation[] */
	private $citations;
	private $confidence;
	private $refused;
	private $reason;

	/** @param Citation[] $citations */
	private function __construct( string $answer, array $citations, float $confidence, bool $refused, string $reason ) {
		$this->answer     = $answer;
		$this->citations  = $citations;
		$this->confidence = max( 0.0, min( 1.0, $confidence ) );
		$this->refused    = $refused;
		$this->reason     = $reason;
	}

	/** @param Citation[] $citations */
	public static function answered( string $answer, array $citations, float $confidence ): self {
		return new self( $answer, $citations, $confidence, false, '' );
	}

	public static function refused( string $message, string $reason ): self {
		return new self( $message, array(), 0.0, true, $reason );
	}

	public function isRefused(): bool {
		return $this->refused; }

	/** @return array<string, mixed> */
	public function toArray(): array {
		return array(
			'answer'     => $this->answer,
			'citations'  => array_map(
				static function ( Citation $citation ): array {
					return $citation->toArray(); },
				$this->citations
			),
			'confidence' => $this->confidence,
			'refused'    => $this->refused,
			'reason'     => $this->reason,
		);
	}
}
