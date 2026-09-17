<?php

declare(strict_types=1);

namespace KBMS\Tests\Unit\Audit;

use KBMS\Audit\AuditLogger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuditLoggerTest extends TestCase
{
    public function testSensitiveContextIsRecursivelyRedacted(): void
    {
        $reflection = new ReflectionClass(AuditLogger::class);
        $logger = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('redact');
        $method->setAccessible(true);

        $result = $method->invoke($logger, [
            'operation' => 'index',
            'api_key' => 'secret-value',
            'nested' => ['authorization_header' => 'Bearer secret'],
        ]);

        self::assertSame('index', $result['operation']);
        self::assertSame('[redacted]', $result['api_key']);
        self::assertSame('[redacted]', $result['nested']['authorization_header']);
    }
}
