<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use PHPUnit\Framework\ExpectationFailedException;
use Temporal\Testing\SearchAttributeMocker;
use Temporal\Tests\TestCase;
use Temporal\Worker\SearchAttributeInvocationCache\InMemorySearchAttributeInvocationCache;

final class SearchAttributeMockerTestCase extends TestCase
{
    private InMemorySearchAttributeInvocationCache $cache;
    private SearchAttributeMocker $mocker;

    protected function setUp(): void
    {
        $this->cache = new InMemorySearchAttributeInvocationCache();
        $this->mocker = new SearchAttributeMocker($this->cache);

        parent::setUp();
    }

    public function testAssertUpsertedAndValue(): void
    {
        $this->cache->recordUpsert('CustomKeyword', ['operation' => 'set', 'type' => 'keyword', 'value' => 'CustomValue']);

        $this->mocker->assertUpserted('CustomKeyword');
        $this->mocker->assertUpsertedValue('CustomKeyword', 'CustomValue');
        self::assertTrue($this->mocker->wasUpserted('CustomKeyword'));
        self::assertSame('CustomValue', $this->mocker->getUpserted('CustomKeyword'));
    }

    public function testAssertUpsertedValueIsStrict(): void
    {
        $this->cache->recordUpsert('CustomInt', ['operation' => 'set', 'type' => 'int64', 'value' => 42]);

        $this->mocker->assertUpsertedValue('CustomInt', 42);

        $this->expectException(ExpectationFailedException::class);
        $this->mocker->assertUpsertedValue('CustomInt', '42');
    }

    public function testGetUpsertedReturnsRawValue(): void
    {
        $when = new \DateTimeImmutable('2026-07-28T12:00:00.500000+00:00');
        $this->cache->recordUpsert('When', ['operation' => 'set', 'type' => 'datetime', 'value' => $when]);

        self::assertSame($when, $this->mocker->getUpserted('When'));
        $this->mocker->assertUpsertedValue('When', $when);
    }

    public function testAssertUpsertedValueComparesDatetimeByValue(): void
    {
        $stored = new \DateTimeImmutable('2026-07-28T12:00:00.500000+00:00');
        $this->cache->recordUpsert('When', ['operation' => 'set', 'type' => 'datetime', 'value' => $stored]);

        $expected = new \DateTimeImmutable('2026-07-28T12:00:00.500000+00:00');
        self::assertNotSame($stored, $expected);
        $this->mocker->assertUpsertedValue('When', $expected);
    }

    public function testAssertUnset(): void
    {
        $this->cache->recordUpsert('Removed', ['operation' => 'unset', 'type' => 'keyword']);

        $this->mocker->assertUnset('Removed');
        self::assertTrue($this->mocker->wasUpserted('Removed'));
        self::assertNull($this->mocker->getUpserted('Removed'));
    }

    public function testCollisionAndNumericNames(): void
    {
        $this->cache->recordUpsert('index', ['operation' => 'set', 'type' => 'keyword', 'value' => 'idx']);
        $this->cache->recordUpsert('2024', ['operation' => 'set', 'type' => 'keyword', 'value' => 'year']);
        $this->cache->recordUpsert('Other', ['operation' => 'set', 'type' => 'keyword', 'value' => 'o']);

        $this->mocker->assertUpsertedValue('index', 'idx');
        $this->mocker->assertUpsertedValue('2024', 'year');
        self::assertTrue($this->mocker->wasUpserted('index'));

        $all = $this->mocker->getUpsertedAttributes();
        self::assertArrayHasKey('index', $all);
        self::assertCount(3, $all);
    }

    public function testAssertNotUpsertedAndEscapeHatch(): void
    {
        $this->cache->recordUpsert('CustomInt', ['operation' => 'set', 'type' => 'int64', 'value' => 42]);

        $this->mocker->assertNotUpserted('Missing');

        $all = $this->mocker->getUpsertedAttributes();
        self::assertArrayHasKey('CustomInt', $all);
        self::assertSame(42, $all['CustomInt']['value']);
    }

    public function testClear(): void
    {
        $this->cache->recordUpsert('CustomKeyword', ['operation' => 'set', 'type' => 'keyword', 'value' => 'v']);

        $this->mocker->clear();

        self::assertFalse($this->mocker->wasUpserted('CustomKeyword'));
        self::assertSame([], $this->mocker->getUpsertedAttributes());
    }
}
