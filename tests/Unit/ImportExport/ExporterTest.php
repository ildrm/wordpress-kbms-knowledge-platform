<?php

declare(strict_types=1);

namespace KBMS\Tests\Unit\ImportExport;

use KBMS\ImportExport\Exporter;
use PHPUnit\Framework\TestCase;

final class ExporterTest extends TestCase
{
    /** @dataProvider unsafeCells */
    public function testCsvFormulaCellsAreNeutralized(string $input): void
    {
        self::assertStringStartsWith("'", Exporter::csvSafe($input));
    }

    /** @return array<string, array{string}> */
    public static function unsafeCells(): array
    {
        return [
            'formula' => ['=1+1'],
            'plus' => ['+SUM(A1:A2)'],
            'minus' => ['-2+3'],
            'at' => ['@cmd'],
            'tab' => ["\t=cmd"],
        ];
    }

    public function testOrdinaryCsvTextIsUnchanged(): void
    {
        self::assertSame('Deployment guide', Exporter::csvSafe('Deployment guide'));
    }
}
