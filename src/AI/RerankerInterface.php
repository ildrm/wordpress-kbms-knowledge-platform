<?php

declare(strict_types=1);

namespace KBMS\AI;

use KBMS\Search\SearchHit;

interface RerankerInterface {

	/**
	 * Reranking receives only permission-filtered hits and may only reorder
	 * those same objects; the pipeline rejects replacement or injected hits.
	 *
	 * @param SearchHit[] $hits
	 * @return SearchHit[]
	 */
	public function rerank( string $question, array $hits ): array;
}
