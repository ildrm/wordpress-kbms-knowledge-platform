<?php

declare(strict_types=1);

namespace KBMS\Tests\Unit\Relations;

use KBMS\Relations\RelationshipTypes;
use PHPUnit\Framework\TestCase;

final class RelationshipTypesTest extends TestCase
{
    public function testEveryRelationshipHasAValidRoundTripInverse(): void
    {
        foreach (RelationshipTypes::all() as $type) {
            self::assertTrue(RelationshipTypes::valid(RelationshipTypes::inverse($type)));
            self::assertSame($type, RelationshipTypes::inverse(RelationshipTypes::inverse($type)));
        }
    }

    public function testDependencyRelationshipsAreAcyclic(): void
    {
        self::assertTrue(RelationshipTypes::isAcyclic('depends_on'));
        self::assertFalse(RelationshipTypes::isAcyclic('related_to'));
    }
}

