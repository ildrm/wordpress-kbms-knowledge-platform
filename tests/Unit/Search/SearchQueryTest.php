<?php

declare(strict_types=1);

namespace KBMS\Tests\Unit\Search;

use KBMS\Search\SearchQuery;
use KBMS\Search\SearchHit;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    public function test_pagination_is_bounded(): void
    {
        $query = new SearchQuery('deployment', 7, 0, 500);

        self::assertSame(1, $query->page());
        self::assertSame(50, $query->perPage());
        self::assertSame(7, $query->userId());
    }

    public function test_empty_query_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchQuery('   ', 1);
    }

    public function test_unsafe_result_url_is_removed(): void
    {
        $hit = new SearchHit(1, 'Title', 'Excerpt', 'javascript:alert(1)', 1.0);
        self::assertSame('', $hit->url());
    }
}
