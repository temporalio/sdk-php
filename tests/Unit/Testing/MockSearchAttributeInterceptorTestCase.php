<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Internal\Transport\Request\UpsertSearchAttributes;
use Temporal\Internal\Transport\Request\UpsertTypedSearchAttributes;
use Temporal\Testing\MockSearchAttributeInterceptor;
use Temporal\Tests\TestCase;
use Temporal\Worker\SearchAttributeInvocationCache\InMemorySearchAttributeInvocationCache;

use function React\Promise\resolve;

final class MockSearchAttributeInterceptorTestCase extends TestCase
{
    private InMemorySearchAttributeInvocationCache $cache;
    private MockSearchAttributeInterceptor $interceptor;

    protected function setUp(): void
    {
        $this->cache = new InMemorySearchAttributeInvocationCache();
        $this->interceptor = new MockSearchAttributeInterceptor($this->cache);

        parent::setUp();
    }

    public function testTypedUpsertIsSwallowedAndRecorded(): void
    {
        $request = new UpsertTypedSearchAttributes([
            SearchAttributeKey::forKeyword('CustomKeyword')->valueSet('CustomValue'),
            SearchAttributeKey::forInteger('CustomInt')->valueSet(42),
        ]);

        $nextCalled = false;
        $result = $this->interceptor->handleOutboundRequest(
            $request,
            static function () use (&$nextCalled) {
                $nextCalled = true;
                return resolve('should-not-happen');
            },
        );

        self::assertFalse($nextCalled);

        $resolved = 'unresolved';
        $result->then(static function ($value) use (&$resolved): void {
            $resolved = $value;
        });
        self::assertNull($resolved);

        self::assertTrue($this->cache->wasUpserted('CustomKeyword'));
        self::assertSame('set', $this->cache->getUpsert('CustomKeyword')['operation']);
        self::assertSame('CustomValue', $this->cache->getUpsert('CustomKeyword')['value']);
        self::assertSame(42, $this->cache->getUpsert('CustomInt')['value']);
    }

    public function testTypedUnsetIsRecordedAsUnset(): void
    {
        $request = new UpsertTypedSearchAttributes([
            SearchAttributeKey::forKeyword('Removed')->valueUnset(),
        ]);

        $this->interceptor->handleOutboundRequest($request, static fn() => resolve(null));

        $entry = $this->cache->getUpsert('Removed');
        self::assertSame('unset', $entry['operation']);
        self::assertArrayNotHasKey('value', $entry);
    }

    public function testDatetimeValueIsStoredRawNotStringified(): void
    {
        $when = new \DateTimeImmutable('2026-07-28T12:00:00.500000+00:00');
        $request = new UpsertTypedSearchAttributes([
            SearchAttributeKey::forDatetime('When')->valueSet($when),
        ]);

        $this->interceptor->handleOutboundRequest($request, static fn() => resolve(null));

        self::assertSame($when, $this->cache->getUpsert('When')['value']);
    }

    public function testCollisionAndNumericNamesRecordCleanly(): void
    {
        $request = new UpsertTypedSearchAttributes([
            SearchAttributeKey::forKeyword('index')->valueSet('idx'),
            SearchAttributeKey::forKeyword('2024')->valueSet('year'),
            SearchAttributeKey::forKeyword('Other')->valueSet('o'),
        ]);

        $this->interceptor->handleOutboundRequest($request, static fn() => resolve(null));

        self::assertSame('idx', $this->cache->getUpsert('index')['value']);
        self::assertSame('year', $this->cache->getUpsert('2024')['value']);
        self::assertTrue($this->cache->wasUpserted('index'));
        self::assertCount(3, $this->cache->all());
    }

    public function testUntypedUpsertPassesThroughAndForwardsResult(): void
    {
        $request = new UpsertSearchAttributes(['attr1' => 'value']);

        $nextCalled = false;
        $result = $this->interceptor->handleOutboundRequest(
            $request,
            static function ($req) use (&$nextCalled) {
                $nextCalled = true;
                return resolve($req);
            },
        );

        self::assertTrue($nextCalled);
        self::assertFalse($this->cache->wasUpserted('attr1'));

        $forwarded = null;
        $result->then(static function ($value) use (&$forwarded): void {
            $forwarded = $value;
        });
        self::assertSame($request, $forwarded);
    }
}
