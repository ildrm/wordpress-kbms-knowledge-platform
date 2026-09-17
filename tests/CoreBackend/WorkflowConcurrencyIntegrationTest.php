<?php

declare(strict_types=1);

namespace KBMS\Tests\CoreBackend;

use KBMS\Database\Migrator;
use KBMS\Workflow\WorkflowRepository;

final class WorkflowConcurrencyIntegrationTest extends \WP_UnitTestCase
{
    public function testCompareAndSwapRejectsStaleState(): void
    {
        global $wpdb;
        (new Migrator($wpdb))->migrate();
        $repository = new WorkflowRepository($wpdb);
        $workflow = $repository->create('Review', 'draft', [
            'draft' => [],
            'review' => [],
            'published' => [],
        ], [
            ['from' => 'draft', 'to' => 'review'],
            ['from' => 'review', 'to' => 'published'],
        ]);
        $postId = self::factory()->post->create(['post_type' => 'kbms_item']);
        $wpdb->insert($wpdb->prefix . 'kbms_knowledge_meta', [
            'post_id' => $postId,
            'space_id' => 1,
            'workflow_id' => $workflow->id,
            'workflow_state' => 'draft',
            'updated_at' => current_time('mysql', true),
        ]);

        self::assertTrue($repository->compareAndSwapState($postId, $workflow->id, 'draft', 'review', 1, ''));
        self::assertFalse($repository->compareAndSwapState($postId, $workflow->id, 'draft', 'review', 2, 'stale'));
    }
}
