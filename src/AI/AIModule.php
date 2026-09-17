<?php

declare(strict_types=1);

namespace KBMS\AI;

use KBMS\Core\Hookable;

final class AIModule implements Hookable {

	private $pipeline;

	public function __construct( GroundedRagPipeline $pipeline ) {
		$this->pipeline = $pipeline;
	}

	public function register(): void {
		add_filter( 'kbms_rag_pipeline', array( $this, 'pipeline' ) );
	}

	public function registerHooks(): void {
		$this->register();
	}

	/** @param mixed $current */
	public function pipeline( $current ): GroundedRagPipeline {
		return $this->pipeline;
	}
}
