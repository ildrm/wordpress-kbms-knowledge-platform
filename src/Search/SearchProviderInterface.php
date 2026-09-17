<?php

declare(strict_types=1);

namespace KBMS\Search;

interface SearchProviderInterface {

	public function search( SearchQuery $query ): SearchResult;
}
