<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Experiments\Fibers\FiberProxy;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;

#[CoversClass(FiberProxy::class)]
final class FiberProxyTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setCurrentContext(null);
    }

    public function testCallDelegatesToInnerCallMagic(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $inner = new class ($promise) {
            public string $calledMethod = '';

            /** @var array<int, mixed> */
            public array $calledArgs = [];

            public function __construct(private readonly PromiseInterface $result) {}

            public function __call(string $method, array $args): mixed
            {
                $this->calledMethod = $method;
                $this->calledArgs = $args;
                return $this->result;
            }
        };

        Facade::setCurrentContext(null);
        $proxy = new FiberProxy($inner);

        $this->expectException(OutOfContextException::class);

        try {
            $proxy->anyMethod('a', 1);
        } finally {
            self::assertSame('anyMethod', $inner->calledMethod);
            self::assertSame(['a', 1], $inner->calledArgs);
        }
    }

    public function testCallSuspendsInsideFiberWhenInnerReturnsPromise(): void
    {
        $context = $this->makeScopeContextStub(true);
        $promise = $this->createMock(PromiseInterface::class);
        $inner = new class ($promise) {
            public function __construct(private readonly PromiseInterface $result) {}

            public function __call(string $method, array $args): mixed
            {
                return $this->result;
            }
        };

        $proxy = new FiberProxy($inner);

        $fiber = new \Fiber(static function () use ($context, $proxy): mixed {
            Facade::setCurrentContext($context);
            return $proxy->doStuff();
        });

        $suspended = $fiber->start();
        self::assertSame($promise, $suspended);

        $fiber->resume(42);
        self::assertSame(42, $fiber->getReturn());
    }

    public function testCallThrowsLogicExceptionWhenInnerReturnsNonPromise(): void
    {
        $inner = new class () {
            public function __call(string $method, array $args): mixed
            {
                return 'not-a-promise';
            }
        };
        $proxy = new FiberProxy($inner);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'FiberProxy expects the inner proxy to return a PromiseInterface; got string.',
        );

        $proxy->anyMethod();
    }

    public function testCallPropagatesExceptionThrownIntoFiber(): void
    {
        $context = $this->makeScopeContextStub(true);
        $promise = $this->createMock(PromiseInterface::class);
        $inner = new class ($promise) {
            public function __construct(private readonly PromiseInterface $result) {}

            public function __call(string $method, array $args): mixed
            {
                return $this->result;
            }
        };

        $proxy = new FiberProxy($inner);

        $fiber = new \Fiber(static function () use ($context, $proxy): mixed {
            Facade::setCurrentContext($context);
            return $proxy->doStuff();
        });

        $fiber->start();

        $thrown = null;
        try {
            $fiber->throw(new \RuntimeException('rejected'));
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(\RuntimeException::class, $thrown);
        self::assertSame('rejected', $thrown->getMessage());
        self::assertTrue($fiber->isTerminated());
    }

    private function makeScopeContextStub(bool $fiberMode): ScopeContext
    {
        $context = (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
        $context->setFiberMode($fiberMode);
        return $context;
    }
}
