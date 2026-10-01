<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common\SearchAttributes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Common\SearchAttributes\ValueType;

#[CoversClass(ValueType::class)]
final class ValueTypeTestCase extends TestCase
{
    public function testEveryValueTypeRoundTripsThroughMetadataName(): void
    {
        foreach (ValueType::cases() as $type) {
            self::assertSame($type, ValueType::fromMetadata($type->metadataName()));
        }
    }
}
