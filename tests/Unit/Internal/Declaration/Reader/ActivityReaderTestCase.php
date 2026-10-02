<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Declaration\Reader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Tests\Unit\Activity\DummyActivity;

#[CoversClass(ActivityReader::class)]
final class ActivityReaderTestCase extends TestCase
{
    public function testPrototypesAreReadOncePerClass(): void
    {
        $reader = new ActivityReader(new AttributeReader());

        $first = $reader->fromClass(DummyActivity::class);

        self::assertSame('DummyActivityDoNothing', $first[0]->getID());
        self::assertSame($first, $reader->fromClass(DummyActivity::class));
        self::assertSame($first[0], $reader->fromClass(DummyActivity::class)[0]);
    }

    public function testFactoryOnARegisteredPrototypeDoesNotChangeTheCachedOne(): void
    {
        $reader = new ActivityReader(new AttributeReader());

        $registered = $reader->fromClass(DummyActivity::class)[0]->withFactory(static fn(): DummyActivity => new DummyActivity());

        self::assertNotNull($registered->getFactory());
        self::assertNull($reader->fromClass(DummyActivity::class)[0]->getFactory());
    }
}
