<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Interceptor;

use Temporal\Interceptor\Header;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowInbound\InitInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Workflow\WorkflowInfo;

/**
 * @group unit
 * @group interceptor
 */
class InitPipelineTestCase extends AbstractUnit
{
    public function testInitCalledThroughPipeline(): void
    {
        $called = false;

        $interceptor = new class($called) implements WorkflowInboundCallsInterceptor {
            use WorkflowInboundCallsInterceptorTrait;

            public function __construct(private bool &$called) {}

            public function init(InitInput $input, callable $next): void
            {
                $this->called = true;
                $next($input);
            }
        };

        $provider = new SimplePipelineProvider([$interceptor]);
        $pipeline = $provider->getPipeline(WorkflowInboundCallsInterceptor::class);

        $pipeline->with(
            static function (InitInput $_input): void {},
            'init',
        )(new InitInput(new WorkflowInfo(), Header::empty()));

        self::assertTrue($called);
    }

    public function testInitInterceptorChainOrder(): void
    {
        $order = [];

        $first = new class($order) implements WorkflowInboundCallsInterceptor {
            use WorkflowInboundCallsInterceptorTrait;

            public function __construct(private array &$order) {}

            public function init(InitInput $input, callable $next): void
            {
                $this->order[] = 'first:before';
                $next($input);
                $this->order[] = 'first:after';
            }
        };

        $second = new class($order) implements WorkflowInboundCallsInterceptor {
            use WorkflowInboundCallsInterceptorTrait;

            public function __construct(private array &$order) {}

            public function init(InitInput $input, callable $next): void
            {
                $this->order[] = 'second:before';
                $next($input);
                $this->order[] = 'second:after';
            }
        };

        $provider = new SimplePipelineProvider([$first, $second]);
        $pipeline = $provider->getPipeline(WorkflowInboundCallsInterceptor::class);

        $pipeline->with(
            static function (InitInput $_input) use (&$order): void {
                $order[] = 'handler';
            },
            'init',
        )(new InitInput(new WorkflowInfo(), Header::empty()));

        self::assertSame(['first:before', 'second:before', 'handler', 'second:after', 'first:after'], $order);
    }

    public function testInitInterceptorCanModifyInput(): void
    {
        $interceptor = new class implements WorkflowInboundCallsInterceptor {
            use WorkflowInboundCallsInterceptorTrait;

            public function init(InitInput $input, callable $next): void
            {
                $next($input->with(header: $input->header->withValue('intercepted', 'yes')));
            }
        };

        $provider = new SimplePipelineProvider([$interceptor]);
        $pipeline = $provider->getPipeline(WorkflowInboundCallsInterceptor::class);

        $receivedInput = null;
        $pipeline->with(
            static function (InitInput $input) use (&$receivedInput): void {
                $receivedInput = $input;
            },
            'init',
        )(new InitInput(new WorkflowInfo(), Header::empty()));

        self::assertNotNull($receivedInput);
        self::assertSame('yes', $receivedInput->header->getValue('intercepted'));
    }

    public function testDefaultTraitPassesThrough(): void
    {
        $interceptor = new class implements WorkflowInboundCallsInterceptor {
            use WorkflowInboundCallsInterceptorTrait;
        };

        $provider = new SimplePipelineProvider([$interceptor]);
        $pipeline = $provider->getPipeline(WorkflowInboundCallsInterceptor::class);

        $handlerCalled = false;
        $pipeline->with(
            static function (InitInput $_input) use (&$handlerCalled): void {
                $handlerCalled = true;
            },
            'init',
        )(new InitInput(new WorkflowInfo(), Header::empty()));

        self::assertTrue($handlerCalled);
    }
}
