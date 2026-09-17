<?php

declare(strict_types=1);

namespace KBMS\Tests\Unit\Health;

use KBMS\Health\CallbackHealthCheck;
use KBMS\Health\HealthCheckResult;
use KBMS\Health\HealthStatus;
use PHPUnit\Framework\TestCase;

final class HealthStatusTest extends TestCase
{
    public function test_worst_status_wins(): void
    {
        $health = new HealthStatus(array(
            new CallbackHealthCheck('database', static function (): HealthCheckResult {
                return new HealthCheckResult(HealthCheckResult::GOOD, 'Database', 'Connected.');
            }),
            new CallbackHealthCheck('ai', static function (): HealthCheckResult {
                return new HealthCheckResult(HealthCheckResult::RECOMMENDED, 'AI', 'Provider is disabled.');
            }),
        ));

        self::assertSame(HealthCheckResult::RECOMMENDED, $health->summary()['status']);
    }

    public function test_exception_details_are_not_exposed(): void
    {
        $health = new HealthStatus(array(
            new CallbackHealthCheck('external', static function (): HealthCheckResult {
                throw new \RuntimeException('secret-token-123');
            }),
        ));

        $summary = $health->summary();
        self::assertSame(HealthCheckResult::CRITICAL, $summary['status']);
        self::assertStringNotContainsString('secret-token-123', json_encode($summary, JSON_THROW_ON_ERROR));
    }
}
