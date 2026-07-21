<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Tests\TestCase;
use Temporal\Worker\ActivityInvocationCache\InMemoryActivityInvocationCache;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class ActivityMockerTestCase extends TestCase
{
    private DataConverter $converter;

    protected function setUp(): void
    {
        $this->converter = DataConverter::createDefault();
        parent::setUp();
    }

    public function testConsecutiveCompletionsAdvanceAndRepeatLast(): void
    {
        $cache = new InMemoryActivityInvocationCache($this->converter);
        $cache->saveConsecutiveCompletions('SomeActivity.method', ['first', 'second']);

        self::assertSame('first', $this->executeToValue($cache, []));
        self::assertSame('second', $this->executeToValue($cache, []));
        self::assertSame('second', $this->executeToValue($cache, []));
    }

    public function testMatchesByArguments(): void
    {
        $cache = new InMemoryActivityInvocationCache($this->converter);
        $cache->saveCompletionWhen('SomeActivity.method', ['a'], 'result-a');
        $cache->saveCompletionWhen('SomeActivity.method', ['b'], 'result-b');

        self::assertSame('result-a', $this->executeToValue($cache, ['a']));
        self::assertSame('result-b', $this->executeToValue($cache, ['b']));
    }

    public function testUnmatchedArgumentsReject(): void
    {
        $cache = new InMemoryActivityInvocationCache($this->converter);
        $cache->saveCompletionWhen('SomeActivity.method', ['a'], 'result-a');

        $caught = null;
        $cache->execute($this->invokeActivityRequest(['c']))->then(
            static fn() => self::fail('unmatched arguments must reject'),
            static function (\Throwable $error) use (&$caught): void {
                $caught = $error;
            },
        );

        self::assertInstanceOf(InvalidArgumentException::class, $caught);
        self::assertStringContainsString('No matching expectation', $caught->getMessage());
    }

    public function testLocalActivityRequestIsHandled(): void
    {
        $cache = new InMemoryActivityInvocationCache($this->converter);
        $cache->saveCompletion('JustLocalActivity.echo', 'mocked');

        $local = $this->createMock(ServerRequestInterface::class);
        $local->method('getName')->willReturn('InvokeLocalActivity');
        $local->method('getOptions')->willReturn(['name' => 'JustLocalActivity.echo']);

        self::assertTrue($cache->canHandle($local));
    }

    /**
     * @param list<mixed> $args
     */
    private function executeToValue(InMemoryActivityInvocationCache $cache, array $args): mixed
    {
        $captured = null;
        EncodedValues::decodePromise($cache->execute($this->invokeActivityRequest($args)), 'string')
            ->then(static function ($value) use (&$captured): void {
                $captured = $value;
            });

        return $captured;
    }

    /**
     * @param list<mixed> $args
     */
    private function invokeActivityRequest(array $args): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getName')->willReturn('InvokeActivity');
        $request->method('getOptions')->willReturn(['name' => 'SomeActivity.method']);
        $request->method('getPayloads')->willReturn(EncodedValues::fromValues($args, $this->converter));

        return $request;
    }
}
