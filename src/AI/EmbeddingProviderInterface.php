<?php

declare(strict_types=1);

namespace KBMS\AI;

interface EmbeddingProviderInterface {

	/**
	 * @param string[] $texts
	 * @return array<int, float[]>
	 */
	public function embed( array $texts ): array;
}
