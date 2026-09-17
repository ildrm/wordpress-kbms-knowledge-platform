<?php

declare(strict_types=1);

namespace KBMS\Workflow;

use DomainException;

final class WorkflowDefinition {

	public int $id;
	public string $initialState;

	/** @var array<string,array<string,mixed>> */
	private array $states;

	/** @var list<array<string,mixed>> */
	private array $transitions;

	/**
	 * @param array<string,array<string,mixed>> $states
	 * @param list<array<string,mixed>>         $transitions
	 */
	public function __construct( int $id, string $initialState, array $states, array $transitions ) {
		if ( $id <= 0 || ! isset( $states[ $initialState ] ) ) {
			throw new DomainException( 'Invalid workflow definition.' );
		}
		foreach ( $transitions as $transition ) {
			if ( ! isset( $transition['from'], $transition['to'] ) || ! isset( $states[ $transition['from'] ], $states[ $transition['to'] ] ) ) {
				throw new DomainException( 'Workflow transition references an unknown state.' );
			}
		}
		$this->id           = $id;
		$this->initialState = $initialState;
		$this->states       = $states;
		$this->transitions  = $transitions;
	}

	/** @return array<string,mixed>|null */
	public function transition( string $from, string $to ): ?array {
		foreach ( $this->transitions as $transition ) {
			if ( $transition['from'] === $from && $transition['to'] === $to ) {
				return $transition;
			}
		}
		return null;
	}

	public function hasState( string $state ): bool {
		return isset( $this->states[ $state ] );
	}
}
