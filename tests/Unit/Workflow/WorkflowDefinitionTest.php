<?php

declare(strict_types=1);

namespace KBMS\Tests\Unit\Workflow;

use DomainException;
use KBMS\Workflow\WorkflowDefinition;
use PHPUnit\Framework\TestCase;

final class WorkflowDefinitionTest extends TestCase
{
    public function testFindsExplicitTransition(): void
    {
        $workflow = new WorkflowDefinition(1, 'draft', [
            'draft' => [],
            'review' => [],
        ], [[
            'from' => 'draft',
            'to' => 'review',
            'action' => 'edit',
        ]]);

        self::assertSame('edit', $workflow->transition('draft', 'review')['action']);
        self::assertNull($workflow->transition('review', 'draft'));
    }

    public function testRejectsUnknownTransitionState(): void
    {
        $this->expectException(DomainException::class);
        new WorkflowDefinition(1, 'draft', ['draft' => []], [[
            'from' => 'draft',
            'to' => 'missing',
        ]]);
    }
}
