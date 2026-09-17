<?php

declare(strict_types=1);

namespace KBMS\Tests\Unit\Health;

use KBMS\Health\KnowledgeHealthScorer;
use PHPUnit\Framework\TestCase;

final class KnowledgeHealthScorerTest extends TestCase
{
    public function test_complete_verified_current_item_scores_one_hundred(): void
    {
        $scorer = new KnowledgeHealthScorer(new \DateTimeImmutable('2026-01-01 00:00:00 UTC'));
        $result = $scorer->score(array(
            'title'               => 'Production rollback',
            'content'             => str_repeat('Operational guidance. ', 20),
            'review_at'           => '2026-06-01 00:00:00',
            'verification_status' => 'verified',
            'owner_id'            => 4,
            'reviewer_id'         => 7,
            'space_id'            => 2,
            'type'                => 'runbook',
            'language'            => 'en',
            'confidentiality'     => 'internal',
        ));

        self::assertSame(100.0, $result->score());
    }

    public function test_missing_governance_and_stale_content_scores_low(): void
    {
        $scorer = new KnowledgeHealthScorer(new \DateTimeImmutable('2026-01-01 00:00:00 UTC'));
        $result = $scorer->score(array('title' => 'Thin', 'content' => 'Too short', 'review_at' => '2020-01-01'));

        self::assertLessThan(20.0, $result->score());
    }
}
