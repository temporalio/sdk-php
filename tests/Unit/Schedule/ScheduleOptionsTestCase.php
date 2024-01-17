<?php

declare(strict_types=1);

namespace Schedule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\BackfillPeriod;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\ScheduleOptions;
use Temporal\DataConverter\EncodedCollection;

#[CoversClass(\Temporal\Client\Schedule\ScheduleOptions::class)]
class ScheduleOptionsTestCase extends TestCase
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

    public static function provideEncodedValues(): iterable
    {
        yield 'array' => [['foo' => 'bar'], ['foo' => 'bar']];
        yield 'generator' => [(static fn() => yield from ['foo' => 'bar'])(), ['foo' => 'bar']];
        yield 'encoded collection' => [EncodedCollection::fromValues(['foo' => 'bar']), ['foo' => 'bar']];
        yield 'change array' => [['foo' => 'bar'], ['foo' => 'bar'], ['baz' => 'qux'], ['baz' => 'qux']];
        yield 'change generator' => [
            (static fn() => yield from ['foo' => 'bar'])(),
            ['foo' => 'bar'],
            (static fn() => yield from ['baz' => 'qux'])(),
            ['baz' => 'qux'],
        ];
        yield 'change encoded collection' => [
            EncodedCollection::fromValues(['foo' => 'bar']),
            ['foo' => 'bar'],
            EncodedCollection::fromValues(['baz' => 'qux']),
            ['baz' => 'qux'],
        ];
        yield 'clear' => [[], [], ['foo' => 'bar'], ['foo' => 'bar']];
    }

    #[DataProvider('provideEncodedValues')]
    public function testWithMemo(mixed $values, array $expect, mixed $initValues = null, array $initExpect = []): void
    {
        $init = ScheduleOptions::new();
        $initValues === null or $init = $init->withMemo($initValues);

        $new = $init->withMemo($values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(\count($initExpect), $init->memo, 'init value was not changed');
        $this->assertSame($initExpect, $init->memo->getValues(), 'init value was not changed');
        $this->assertCount(\count($expect), $new->memo);
        $this->assertSame($expect, $new->memo->getValues());
    }

    #[DataProvider('provideEncodedValues')]
    public function testWithSearchAttributes(mixed $values, array $expect, mixed $initValues = null, array $initExpect = []): void
    {
        $init = ScheduleOptions::new();
        $initValues === null or $init = $init->withSearchAttributes($initValues);

        $new = $init->withSearchAttributes($values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(\count($initExpect), $init->searchAttributes, 'init value was not changed');
        $this->assertSame($initExpect, $init->searchAttributes->getValues(), 'init value was not changed');
        $this->assertCount(\count($expect), $new->searchAttributes);
        $this->assertSame($expect, $new->searchAttributes->getValues());
    }
}
