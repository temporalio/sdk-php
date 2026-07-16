<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use Temporal\Interceptor\WorkflowOutboundCalls\SideEffectInput;
use Temporal\Testing\MockSideEffectInterceptor;
use Temporal\Worker\ChildWorkflowInvocationCache\InMemoryChildWorkflowInvocationCache;

final class MockSideEffectInterceptorTestCase extends TestCase
{
    public function testRunsRealClosureWhenNoExpectation(): void
    {
        $cache = new InMemoryChildWorkflowInvocationCache();
        $interceptor = new MockSideEffectInterceptor($cache);

        $result = $interceptor->sideEffect(
            new SideEffectInput(static fn(): int => 7),
            $this->realClosure(),
        );

        self::assertSame(7, $result);
    }

    public function testConsumesExpectationsInOrderThenFallsBackToRealClosure(): void
    {
        $cache = new InMemoryChildWorkflowInvocationCache();
        $cache->saveSideEffect(100);
        $cache->saveSideEffect(200);

        $interceptor = new MockSideEffectInterceptor($cache);
        $input = new SideEffectInput(static fn(): int => 7);

        self::assertSame(100, $interceptor->sideEffect($input, $this->realClosure()));
        self::assertSame(200, $interceptor->sideEffect($input, $this->realClosure()));
        self::assertSame(7, $interceptor->sideEffect($input, $this->realClosure()));
    }

    private function realClosure(): callable
    {
        return static fn(SideEffectInput $input): mixed => ($input->callable)();
    }
}
