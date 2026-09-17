<?php

declare(strict_types=1);

namespace KBMS\Health;

final class KnowledgeHealthScorer {

	/** @var array<string, float> */
	private const WEIGHTS = array(
		'freshness'    => 20.0,
		'verification' => 25.0,
		'completeness' => 15.0,
		'owner'        => 10.0,
		'reviewer'     => 10.0,
		'metadata'     => 20.0,
	);

	/** @var \DateTimeImmutable */
	private $now;

	public function __construct( ?\DateTimeImmutable $now = null ) {
		$this->now = $now ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * @param array<string, mixed> $knowledge Sanitized knowledge metadata and plain-text content.
	 */
	public function score( array $knowledge ): KnowledgeHealthScore {
		$factors = array(
			'freshness'    => $this->isFresh( $knowledge ) ? 1.0 : 0.0,
			'verification' => 'verified' === ( $knowledge['verification_status'] ?? '' ) ? 1.0 : 0.0,
			'completeness' => $this->completeness( $knowledge ),
			'owner'        => ! empty( $knowledge['owner_id'] ) ? 1.0 : 0.0,
			'reviewer'     => ! empty( $knowledge['reviewer_id'] ) ? 1.0 : 0.0,
			'metadata'     => $this->metadataCompleteness( $knowledge ),
		);

		$score = 0.0;
		foreach ( $factors as $factor => $value ) {
			$score += self::WEIGHTS[ $factor ] * $value;
		}
		return new KnowledgeHealthScore( round( $score, 1 ), $factors );
	}

	/** @param array<string, mixed> $knowledge */
	private function isFresh( array $knowledge ): bool {
		$date = (string) ( $knowledge['review_at'] ?? '' );
		if ( '' === $date ) {
			return false;
		}
		try {
			return new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) >= $this->now;
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	/** @param array<string, mixed> $knowledge */
	private function completeness( array $knowledge ): float {
		$title   = trim( (string) ( $knowledge['title'] ?? '' ) );
		$content = trim( strip_tags( (string) ( $knowledge['content'] ?? '' ) ) );
		return ( ( $title !== '' ? 0.25 : 0.0 ) + ( strlen( $content ) >= 200 ? 0.75 : ( strlen( $content ) > 0 ? 0.35 : 0.0 ) ) );
	}

	/** @param array<string, mixed> $knowledge */
	private function metadataCompleteness( array $knowledge ): float {
		$present = 0;
		foreach ( array( 'space_id', 'type', 'language', 'confidentiality' ) as $field ) {
			if ( isset( $knowledge[ $field ] ) && '' !== (string) $knowledge[ $field ] && 0 !== $knowledge[ $field ] ) {
				++$present;
			}
		}
		return $present / 4.0;
	}
}
