<?php

declare(strict_types=1);

namespace Schedule;

use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\BackfillPeriod;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\ScheduleOptions;
use Temporal\DataConverter\EncodedCollection;

/**
 * @covers \Temporal\Client\Schedule\ScheduleOptions
 */
class ScheduleOptionsTest extends TestCase
{
    public function testWithNamespace(): void
    {
        $init = ScheduleOptions::new();

        $new = $init->withNamespace('foo');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('default', $init->namespace, 'default value was not changed');
        $this->assertSame('foo', $new->namespace);
    }

    public function testWithTriggerImmediately(): void
    {
        $init = ScheduleOptions::new();

        $new = $init->withTriggerImmediately(true);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertFalse($init->triggerImmediately, 'default value was not changed');
        $this->assertTrue($new->triggerImmediately);
    }

    public function testWithBackfills(): void
    {
        $init = ScheduleOptions::new();
        $values = [
            BackfillPeriod::new('2021-01-01T00:00:00', '2021-01-02T00:00:00'),
            BackfillPeriod::new('2021-01-03T00:00:00', '2021-01-04T00:00:00', ScheduleOverlapPolicy::BufferOne),
        ];

        $new = $init->withBackfills(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->backfills, 'value was not changed');
        $this->assertSame($values, $new->backfills);
    }

    public function testWithAddedBackfills(): void
    {
        $init = ScheduleOptions::new();
        $values = [
            BackfillPeriod::new('2021-01-01T00:00:00', '2021-01-02T00:00:00'),
            BackfillPeriod::new('2021-01-03T00:00:00', '2021-01-04T00:00:00', ScheduleOverlapPolicy::BufferOne),
        ];

        $new0 = $init->withAddedBackfill($values[0]);
        $new1 = $new0->withAddedBackfill($values[1]);

        $this->assertNotSame($init, $new0, 'immutable method clones object');
        $this->assertNotSame($new1, $new0, 'immutable method clones object');
        $this->assertSame([], $init->backfills, 'default value was not changed');
        $this->assertSame([$values[0]], $new0->backfills);
        $this->assertSame($values, $new1->backfills);
    }

    public function testWithMemoArray(): void
    {
        $init = ScheduleOptions::new();
        $values = [
            'foo' => 'bar',
            'baz' => 'qux',
        ];

        $new = $init->withMemo($values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->memo->getValues(), 'value was not changed');
        $this->assertSame($values, $new->memo->getValues());
    }

    public function testWithMemoGenerator(): void
    {
        $init = ScheduleOptions::new()->withMemo(['foo' => 'bar']);
        $values = [
            'baz' => 'qux',
            'quux' => 'quuz',
        ];

        $new = $init->withMemo((static fn() => yield from $values)());

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(['foo' => 'bar'], $init->memo->getValues(), 'value was not changed');
        $this->assertSame($values, $new->memo->getValues());
    }

    public function testWithMemoEncodedCollection(): void
    {
        $init = ScheduleOptions::new()->withMemo(['foo' => 'bar']);
        $values = [
            'baz' => 'qux',
            'quux' => 'quuz',
        ];

        $new = $init->withMemo(EncodedCollection::fromValues($values));

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(['foo' => 'bar'], $init->memo->getValues(), 'value was not changed');
        $this->assertSame($values, $new->memo->getValues());
    }

    public function testWithSearchAttributesArray(): void
    {
        $init = ScheduleOptions::new();
        $values = [
            'foo' => 'bar',
            'baz' => 'qux',
        ];

        $new = $init->withSearchAttributes($values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->searchAttributes->getValues(), 'value was not changed');
        $this->assertSame($values, $new->searchAttributes->getValues());
    }

    public function testWithSearchAttributesGenerator(): void
    {
        $init = ScheduleOptions::new()->withSearchAttributes(['foo' => 'bar']);
        $values = [
            'baz' => 'qux',
            'quux' => 'quuz',
        ];

        $new = $init->withSearchAttributes((static fn() => yield from $values)());

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(['foo' => 'bar'], $init->searchAttributes->getValues(), 'value was not changed');
        $this->assertSame($values, $new->searchAttributes->getValues());
    }

    public function testWithSearchAttributesEncodedCollection(): void
    {
        $init = ScheduleOptions::new()->withSearchAttributes(['foo' => 'bar']);
        $values = [
            'baz' => 'qux',
            'quux' => 'quuz',
        ];

        $new = $init->withSearchAttributes(EncodedCollection::fromValues($values));

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(['foo' => 'bar'], $init->searchAttributes->getValues(), 'value was not changed');
        $this->assertSame($values, $new->searchAttributes->getValues());
    }
}
