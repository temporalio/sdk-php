<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow\Process;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Internal\Workflow\ScopeContext;

#[CoversClass(Scope::class)]
final class ScopeFiberModeLifecycleTestCase extends TestCase
{
    public function testFiberModeResetWhenFiberStartThrowsSynchronously(): void
    {
        $context = $this->makeScopeContext();
        $context->setFiberMode(false);
        $values = EncodedValues::empty();

        $handler = static function () {
            throw new \RuntimeException('synchronous-fail');
        };

        $closure = $this->getFiberHandler($context);

        $threw = null;
        try {
            $closure($values, $handler);
        } catch (\RuntimeException $e) {
            $threw = $e;
        }

        self::assertInstanceOf(\RuntimeException::class, $threw);
        self::assertSame('synchronous-fail', $threw->getMessage());
        self::assertFalse(
            $context->isFiberMode(),
            'fiberMode must be reset to false after Fiber start throws',
        );
    }

    public function testFiberModeResetWhenFiberCompletesSynchronously(): void
    {
        $context = $this->makeScopeContext();
        $values = EncodedValues::empty();

        $handler = static fn() => 'sync-result';

        $closure = $this->getFiberHandler($context);
        $result = $closure($values, $handler);

        self::assertSame('sync-result', $result);
        self::assertFalse(
            $context->isFiberMode(),
            'fiberMode must be reset to false after Fiber completes synchronously',
        );
    }

    public function testFiberModeResetAfterBridgeGeneratorCompletes(): void
    {
        $context = $this->makeScopeContext();
        $values = EncodedValues::empty();

        $handler = static fn() => \Fiber::suspend('first-yield');

        $closure = $this->getFiberHandler($context);
        $generator = $closure($values, $handler);

        self::assertInstanceOf(\Generator::class, $generator);
        self::assertTrue(
            $context->isFiberMode(),
            'fiberMode should still be true while Fiber is suspended',
        );

        $generator->send('resumed');

        self::assertFalse($generator->valid());
        self::assertFalse(
            $context->isFiberMode(),
            'fiberMode must be reset to false after bridge generator finishes',
        );
    }

    public function testFiberModeResetAfterBridgeGeneratorThrows(): void
    {
        $context = $this->makeScopeContext();
        $values = EncodedValues::empty();

        $handler = static fn() => \Fiber::suspend('first-yield');

        $closure = $this->getFiberHandler($context);
        $generator = $closure($values, $handler);

        $threw = null;
        try {
            $generator->throw(new \LogicException('cancel-injection'));
        } catch (\LogicException $e) {
            $threw = $e;
        }

        self::assertInstanceOf(\LogicException::class, $threw);
        self::assertFalse(
            $context->isFiberMode(),
            'fiberMode must be reset to false after bridge generator finally',
        );
    }

    private function makeScopeContext(): ScopeContext
    {
        return (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
    }

    /**
     * Extracts {@see Scope::createFiberHandler} as a callable that accepts
     * `(ValuesInterface, callable $handler): mixed`, with the handler injected
     * via the closure binding.
     */
    private function getFiberHandler(ScopeContext $context): \Closure
    {
        $scope = (new \ReflectionClass(Scope::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Scope::class, 'createFiberHandler');

        return static function ($values, callable $handler) use ($scope, $method, $context) {
            $closure = $method->invoke($scope, $handler, $context);
            return $closure($values);
        };
    }
}
