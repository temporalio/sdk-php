<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\TypedSearchAttributes;
use PHPUnit\Framework\TestCase;

class TypedSearchAttributesTestCase extends TestCase
{
    public function testCount(): void
    {
        $collection1 = TypedSearchAttributes::empty();
        $collection2 = $collection1->withValue(SearchAttributeKey::forBool('name1'), true);
        $collection3 = $collection2->withValue(SearchAttributeKey::forBool('name2'), false);

        self::assertCount(0, $collection1);
        self::assertCount(1, $collection2);
        self::assertCount(2, $collection3);
    }

    public function testWithoutValueImmutability(): void
    {
        $collection1 = TypedSearchAttributes::empty();
        $collection2 = $collection1->withValue(SearchAttributeKey::forBool('name1'), true);
        $collection3 = $collection2->withoutValue(SearchAttributeKey::forBool('name1'));
        $collection4 = $collection3->withoutValue(SearchAttributeKey::forBool('name1'));

        self::assertNotSame($collection1, $collection2);
        self::assertNotSame($collection2, $collection3);
        self::assertNotSame($collection1, $collection3);
        self::assertNotSame($collection3, $collection4);

        self::assertFalse($collection1->hasKey(SearchAttributeKey::forBool('name1')));
        self::assertTrue($collection2->hasKey(SearchAttributeKey::forBool('name1')));
        self::assertFalse($collection2->hasKey(SearchAttributeKey::forBool('name2')));
        self::assertFalse($collection3->hasKey(SearchAttributeKey::forBool('name1')));
    }

    public function testWithValueImmutability(): void
    {
        $collection1 = TypedSearchAttributes::empty();
        $collection2 = $collection1->withValue(SearchAttributeKey::forBool('name1'), true);
        $collection3 = $collection2->withValue(SearchAttributeKey::forBool('name2'), false);

        self::assertNotSame($collection1, $collection2);
        self::assertNotSame($collection2, $collection3);
        self::assertNotSame($collection1, $collection3);

        self::assertFalse($collection1->hasKey(SearchAttributeKey::forBool('name1')));
        self::assertTrue($collection2->hasKey(SearchAttributeKey::forBool('name1')));
        self::assertFalse($collection2->hasKey(SearchAttributeKey::forBool('name2')));
        self::assertTrue($collection3->hasKey(SearchAttributeKey::forBool('name1')));
        self::assertTrue($collection3->hasKey(SearchAttributeKey::forBool('name2')));
    }

    public function testWithValueOverride(): void
    {
        $collection = TypedSearchAttributes::empty()
            ->withValue(SearchAttributeKey::forBool('name2'), false)
            ->withValue(SearchAttributeKey::forBool('name2'), true);

        self::assertCount(1, $collection);
        self::assertTrue($collection->offsetGet('name2'));
    }

    public function testWithUntypedValueImmutability(): void
    {
        $collection1 = TypedSearchAttributes::empty();
        $collection2 = $collection1->withUntypedValue('name1', true);
        $collection3 = $collection2->withUntypedValue('name2', false);

        self::assertNotSame($collection1, $collection2);
        self::assertNotSame($collection2, $collection3);
        self::assertNotSame($collection1, $collection3);

        self::assertFalse($collection1->hasKey(SearchAttributeKey::forBool('name1')));
        self::assertTrue($collection2->hasKey(SearchAttributeKey::forBool('name1')));
        self::assertFalse($collection2->hasKey(SearchAttributeKey::forBool('name2')));
        self::assertTrue($collection3->hasKey(SearchAttributeKey::forBool('name1')));
        self::assertTrue($collection3->hasKey(SearchAttributeKey::forBool('name2')));
    }

    public function testIteratorAggregate(): void
    {
        $collection = TypedSearchAttributes::empty()
            ->withValue(SearchAttributeKey::forBool('name1'), true)
            ->withValue(SearchAttributeKey::forBool('name2'), false)
            ->withValue(SearchAttributeKey::forInteger('name3'), 42);

        foreach ($collection as $key => $value) {
            $this->assertInstanceOf(SearchAttributeKey::class, $key);
            $this->assertIsScalar($value);
        }

        self::assertSame([true, false, 42], \iterator_to_array($collection, false));
    }

    public function testOffsetGet(): void
    {
        $collection1 = TypedSearchAttributes::empty();
        $collection2 = $collection1->withValue(SearchAttributeKey::forBool('name1'), true);
        $collection3 = $collection2->withValue(SearchAttributeKey::forBool('name2'), false);

        self::assertNull($collection1->offsetGet('name1'));
        self::assertTrue($collection2->offsetGet('name1'));
        self::assertNull($collection2->offsetGet('name2'));
        self::assertTrue($collection3->offsetGet('name1'));
        self::assertFalse($collection3->offsetGet('name2'));
    }

    public function testGet(): void
    {
        $collection = TypedSearchAttributes::empty()
            ->withValue($v1 = SearchAttributeKey::forBool('name1'), true)
            ->withValue($v2 = SearchAttributeKey::forBool('name2'), false);
        $v3 = SearchAttributeKey::forInteger('name3');

        self::assertTrue($collection->get($v1));
        self::assertFalse($collection->get($v2));
        self::assertNull($collection->get($v3));
    }

    public function testHasKey(): void
    {
        $collection = TypedSearchAttributes::empty()
            ->withValue($v1 = SearchAttributeKey::forBool('name1'), true)
            ->withValue($v2 = SearchAttributeKey::forBool('name2'), false);
        $v3 = SearchAttributeKey::forInteger('name3');

        self::assertTrue($collection->hasKey($v1));
        self::assertTrue($collection->hasKey($v2));
        self::assertFalse($collection->hasKey($v3));
    }

    public function testEmpty(): void
    {
        $collection = TypedSearchAttributes::empty();

        self::assertCount(0, $collection);
        self::assertNull($collection->offsetGet('name'));
        self::assertSame([], \iterator_to_array($collection, false));
    }

    public function testFromJsonArray(): void
    {
        $collection = TypedSearchAttributes::fromJsonArray([
            'name1' => [
                'type' => 'bool',
                'value' => true,
            ],
            'name2' => [
                'type' => 'bool',
                'value' => false,
            ],
            'name3' => [
                'type' => 'int64',
                'value' => 42,
            ],
            'name4' => [
                'type' => 'keyword',
                'value' => 'bar',
            ],
            'name5' => [
                'type' => 'float64',
                'value' => 3.14,
            ],
            'name6' => [
                'type' => 'string',
                'value' => 'foo',
            ],
            'name7' => [
                'type' => 'datetime',
                'value' => '2021-01-01T00:00:00+00:00',
            ],
            'name8' => [
                'type' => 'keyword_list',
                'value' => ['foo', 'bar'],
            ],
        ]);

        self::assertCount(8, $collection);
        self::assertTrue($collection->get(SearchAttributeKey::forBool('name1')));
        self::assertFalse($collection->get(SearchAttributeKey::forBool('name2')));
        self::assertSame(42, $collection->get(SearchAttributeKey::forInteger('name3')));
        self::assertSame('bar', $collection->get(SearchAttributeKey::forKeyword('name4')));
        self::assertSame(3.14, $collection->get(SearchAttributeKey::forFloat('name5')));
        self::assertSame('foo', $collection->get(SearchAttributeKey::forText('name6')));
        self::assertInstanceOf(\DateTimeImmutable::class, $collection->get(SearchAttributeKey::forDatetime('name7')));
        self::assertSame(
            '2021-01-01T00:00:00+00:00',
            $collection->get(SearchAttributeKey::forDatetime('name7'))->format(DATE_RFC3339),
        );
        self::assertSame(['foo', 'bar'], $collection->get(SearchAttributeKey::forKeywordList('name8')));
    }

    public function testFromUntypedCollection(): void
    {
        $collection = TypedSearchAttributes::fromCollection([
            'name1' => true,
            'name2' => false,
            'name3' => 42,
            'name4' => 'bar',
            'name5' => 3.14,
            'name7' => new \DateTimeImmutable('2021-01-01T00:00:00+00:00'),
            'name8' => ['foo', 'bar'],
        ]);

        self::assertCount(7, $collection);
        self::assertTrue($collection->get(SearchAttributeKey::forBool('name1')));
        self::assertFalse($collection->get(SearchAttributeKey::forBool('name2')));
        self::assertSame(42, $collection->get(SearchAttributeKey::forInteger('name3')));
        self::assertSame('bar', $collection->get(SearchAttributeKey::forText('name4')));
        self::assertSame(3.14, $collection->get(SearchAttributeKey::forFloat('name5')));
        self::assertInstanceOf(\DateTimeImmutable::class, $collection->get(SearchAttributeKey::forDatetime('name7')));
        self::assertSame('2021-01-01T00:00:00+00:00', $collection->get(SearchAttributeKey::forDatetime('name7'))->format(DATE_RFC3339));
        self::assertSame(['foo', 'bar'], $collection->get(SearchAttributeKey::forKeywordList('name8')));
    }

    public function testValues()
    {
        $collection = TypedSearchAttributes::empty()
            ->withValue(SearchAttributeKey::forFloat('testFloat'), 1.1)
            ->withValue(SearchAttributeKey::forInteger('testInt'), -2)
            ->withValue(SearchAttributeKey::forBool('testBool'), false)
            ->withValue(SearchAttributeKey::forText('testText'), 'foo')
            ->withValue(SearchAttributeKey::forKeyword('testKeyword'), 'bar')
            ->withValue(SearchAttributeKey::forKeywordList('testKeywordList'), ['baz'])
            ->withValue(
                SearchAttributeKey::forDatetime('testDatetime'),
                new \DateTimeImmutable('2019-01-01T00:00:00Z'),
            );

        self::assertSame(1.1, $collection->offsetGet('testFloat'));

    }
}
