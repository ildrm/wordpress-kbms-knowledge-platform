<?php

declare(strict_types=1);

namespace {
    if (!class_exists('WP_Query')) {
        final class WP_Query
        {
            /** @var array<int, string> */
            public static $events = array();
            /** @var array<int, object> */
            public $posts = array();
            /** @var int */
            public $found_posts = 0;

            /** @param array<string, mixed> $args */
            public function __construct(array $args)
            {
                self::$events[] = 'query:' . implode(',', array_map('strval', (array) ($args['post__in'] ?? array())));
            }
        }
    }
}

namespace KBMS\Tests\Unit\Search {
    use KBMS\Permissions\AuthorizationInterface;
    use KBMS\Search\SearchQuery;
    use KBMS\Search\WordPressSearchProvider;
    use PHPUnit\Framework\TestCase;

    final class WordPressSearchProviderTest extends TestCase
    {
        public function test_authorization_constrains_query_before_execution(): void
        {
            \WP_Query::$events = array();
            $authorization = new class implements AuthorizationInterface {
                public function can(string $action, int $resourceId, int $userId): bool { return true; }
                public function constrainQueryArgs(array $args, int $userId): array
                {
                    \WP_Query::$events[] = 'authorize';
                    $args['post__in'] = array(22);
                    return $args;
                }
            };

            $provider = new WordPressSearchProvider($authorization);
            $result   = $provider->search(new SearchQuery('incident', 9));

            self::assertSame(array('authorize', 'query:22'), \WP_Query::$events);
            self::assertSame(0, $result->total());
            self::assertNotSame('', $result->scopeFingerprint());
        }
    }
}
