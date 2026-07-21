<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\Type;
use Temporal\Interceptor\Trait\WorkflowOutboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteNexusOperationInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\NexusOperationPrototype;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Workflow\NexusServiceProxy;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\NexusOperationStubInterface;
use Temporal\Workflow\WorkflowContextInterface;

use function React\Promise\resolve;

/**
 * @group unit
 * @group nexus
 */
final class NexusServiceProxyTestCase extends TestCase
{
    public function testInterceptorEndpointRewriteChangesOutgoingOptions(): void
    {
        $captured = null;
        $proxy = $this->makeProxy(
            $this->makeContext($captured),
            new class implements WorkflowOutboundCallsInterceptor {
                use WorkflowOutboundCallsInterceptorTrait;

                public function executeNexusOperation(
                    ExecuteNexusOperationInput $input,
                    callable $next,
                ): PromiseInterface {
                    return $next($input->with(endpoint: 'rewritten-ep'));
                }
            },
        );

        $proxy->placeOrder('order-1');

        self::assertInstanceOf(NexusOperationOptions::class, $captured);
        self::assertSame('rewritten-ep', $captured->endpoint);
        self::assertSame('OrderService', $captured->service);
    }

    public function testInterceptorServiceRewriteChangesOutgoingOptions(): void
    {
        $captured = null;
        $proxy = $this->makeProxy(
            $this->makeContext($captured),
            new class implements WorkflowOutboundCallsInterceptor {
                use WorkflowOutboundCallsInterceptorTrait;

                public function executeNexusOperation(
                    ExecuteNexusOperationInput $input,
                    callable $next,
                ): PromiseInterface {
                    return $next($input->with(service: 'RewrittenService'));
                }
            },
        );

        $proxy->placeOrder('order-1');

        self::assertInstanceOf(NexusOperationOptions::class, $captured);
        self::assertSame('orig-ep', $captured->endpoint);
        self::assertSame('RewrittenService', $captured->service);
    }

    public function testWithoutInterceptorsOptionsPassThroughUnchanged(): void
    {
        $captured = null;
        $proxy = $this->makeProxy($this->makeContext($captured));

        $proxy->placeOrder('order-1');

        self::assertInstanceOf(NexusOperationOptions::class, $captured);
        self::assertSame('orig-ep', $captured->endpoint);
        self::assertSame('OrderService', $captured->service);
    }

    public function testUnknownMethodThrowsBadMethodCall(): void
    {
        $captured = null;
        $proxy = $this->makeProxy($this->makeContext($captured));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('has no operation method "unknownMethod"');

        $proxy->unknownMethod();
    }

    private function makeProxy(
        WorkflowContextInterface $ctx,
        WorkflowOutboundCallsInterceptor ...$interceptors,
    ): NexusServiceProxy {
        $reflection = new \ReflectionClass(NexusProxyTestService::class);
        $operation = new NexusOperationPrototype(
            name: 'place-order',
            methodName: 'placeOrder',
            inputType: Type::create(Type::TYPE_STRING),
            outputType: Type::create(Type::TYPE_STRING),
            async: false,
            handler: $reflection->getMethod('placeOrder'),
        );

        return new NexusServiceProxy(
            NexusProxyTestService::class,
            new NexusServicePrototype('OrderService', ['place-order' => $operation], $reflection),
            NexusOperationOptions::new()->withEndpoint('orig-ep')->withService('OrderService'),
            $ctx,
            Pipeline::prepare($interceptors),
        );
    }

    private function makeContext(?NexusOperationOptions &$captured): WorkflowContextInterface
    {
        $ctx = $this->createMock(WorkflowContextInterface::class);
        $ctx->method('newUntypedNexusOperationStub')
            ->willReturnCallback(static function (NexusOperationOptions $options) use (&$captured) {
                $captured = $options;
                $stub = new class implements NexusOperationStubInterface {
                    public NexusOperationOptions $options;

                    public function getOptions(): NexusOperationOptions
                    {
                        return $this->options;
                    }

                    public function execute(
                        string $operation,
                        array $args = [],
                        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
                        array $nexusHeaders = [],
                    ): PromiseInterface {
                        return resolve(null);
                    }

                    public function start(
                        string $operation,
                        array $args = [],
                        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
                        array $nexusHeaders = [],
                    ): PromiseInterface {
                        return resolve(null);
                    }
                };
                $stub->options = $options;
                return $stub;
            });

        return $ctx;
    }
}

interface NexusProxyTestService
{
    public function placeOrder(string $order): string;
}
