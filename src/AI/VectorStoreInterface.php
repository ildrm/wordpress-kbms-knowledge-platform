<?php

declare(strict_types=1);

namespace KBMS\AI;

use KBMS\Search\SearchHit;

interface VectorStoreInterface {

	/**
	 * Implementations MUST apply VectorQuery::authorizationFilter() in the
	 * underlying query, before vector candidates are returned.
	 *
	 * @return SearchHit[]
	 */
	public function search( VectorQuery $query ): array;
}
