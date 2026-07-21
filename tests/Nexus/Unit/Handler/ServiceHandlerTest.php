<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\NexusOperationContext;

use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Internal\WorkflowRunOperationToken;
use Temporal\Tests\Nexus\Fixtures\Service\GreetingService;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\AuthInterceptor;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\LoggingInterceptor;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\VoidService;
use Temporal\Tests\Nexus\Support\BindNexusService;
use Temporal\Tests\Nexus\Support\EncodesValues;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use Temporal\Tests\Nexus\Support\MocksAsyncWorkflowClient;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class ServiceHandlerTest extends TestCase
{
    use BindNexusService;
    use EncodesValues;
    use ExceptionAssertions;
    use MocksAsyncWorkflowClient;

    private const NS = 'sample-ns';
    private const TQ = 'sample-tq';

    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
    }

    public function testVoidService(): void
    {
        $serviceInstance = self::bindNexusService(new VoidService());
        self::assertCount(1, $serviceInstance->operationHandlers);
    }

    public function testSyncHandlerReturnsSyncResult(): void
    {
        $handler = self::newGreetingHandler();

        $result = $handler->startOperation(
            $this->newGreetingContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('SomeUser'),
            null,
            new NexusOperationContext(),
        );

        self::assertSame('Hello, SomeUser!', $result->value->getValue(0, 'string'));
    }

    public function testAsyncHandlerReturnsToken(): void
    {
        $handler = self::newGreetingHandler();

        $result = $handler->startOperation(
            $this->newGreetingContext('sayHello2'),
            new OperationStartDetails(requestId: 'r3'),
            self::encode('SomeUser'),
            $this->asyncClient(),
            $this->asyncOperationContext(),
        );

        $expectedToken = WorkflowRunOperationToken::generate(self::NS, GreetingService::WORKFLOW_ID);
        self::assertSame($expectedToken, $result->info->token);
    }

    public function testAsyncHandlerWithoutWorkflowClientSurfacesLogicException(): void
    {
        $handler = self::newGreetingHandler();

        $e = self::assertThrown(\LogicException::class, fn() => $handler->startOperation(
            $this->newGreetingContext('sayHello2'),
            new OperationStartDetails(requestId: 'r3'),
            self::encode('SomeUser'),
            null,
            $this->asyncOperationContext(),
        ));

        self::assertSame(
            'Nexus::getWorkflowClient() requires a WorkflowClient. Async Nexus operations (WorkflowRunOperation)'
            . ' need cluster access; provide a WorkflowClient to the WorkerFactory.',
            $e->getMessage(),
        );
    }

    public function testAsyncHandlerCollectsLinksOnLinkSuffixedInput(): void
    {
        $handler = self::newGreetingHandler();
        $context = $this->newGreetingContext('sayHello2');

        $result = $handler->startOperation(
            $context,
            new OperationStartDetails(requestId: 'r4'),
            self::encode('SomeUser-link'),
            $this->asyncClient(),
            $this->asyncOperationContext(),
        );

        self::assertNotNull($result->info->token);
        $links = $context->links->all();
        self::assertCount(2, $links);
        self::assertSame('http://somepath?k=v', $links[0]->uri);
        self::assertSame('com.example.MyResource', $links[0]->type);
        self::assertSame(
            'temporal:///namespaces/sample-ns/workflows/greeting-workflow/run-1/history'
            . '?referenceType=EventReference&eventType=WorkflowExecutionStarted',
            $links[1]->uri,
        );
        self::assertSame('temporal.api.common.v1.Link.WorkflowEvent', $links[1]->type);
    }

    public function testAuthInterceptorAllowsCallWithValidToken(): void
    {
        $token = 'auth-token';
        $handler = self::newGreetingHandler(new SimplePipelineProvider([
            new AuthInterceptor($token),
            new LoggingInterceptor(),
        ]));

        $result = $handler->startOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello1',
                env: $this->env,
                headers: [AuthInterceptor::AUTH_HEADER => $token],
            ),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('SomeUser'),
            null,
            new NexusOperationContext(),
        );

        self::assertSame('Hello, SomeUser!', $result->value->getValue(0, 'string'));
    }

    public function testAuthInterceptorRejectsCallWithMissingToken(): void
    {
        $handler = self::newGreetingHandler(new SimplePipelineProvider([
            new AuthInterceptor('auth-token'),
            new LoggingInterceptor(),
        ]));

        $this->expectException(HandlerException::class);
        $handler->startOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello1',
                env: $this->env,
            ),
            new OperationStartDetails(requestId: 'r2'),
            self::encode('SomeUser'),
            null,
            new NexusOperationContext(),
        );
    }

    public function testLoggingInterceptorRecordsAuthorizedCallsOnly(): void
    {
        $token = 'auth-token';
        $logger = new LoggingInterceptor();
        $handler = self::newGreetingHandler(new SimplePipelineProvider([
            new AuthInterceptor($token),
            $logger,
        ]));

        $handler->startOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello1',
                env: $this->env,
                headers: [AuthInterceptor::AUTH_HEADER => $token],
            ),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('SomeUser'),
            null,
            new NexusOperationContext(),
        );

        // Auth is before logging, so an unauthorized call never reaches the logger.
        self::assertSame(['sayHello1'], $logger->getOperations());
    }

    public function testUnrecognizedService(): void
    {
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new VoidService())],
        );

        $e = self::assertThrown(HandlerException::class, fn() => $handler->startOperation(
            new OperationContext(service: 'NonExistent', operation: 'op', env: $this->env),
            new OperationStartDetails(requestId: 'r1'),
            EncodedValues::empty(),
            null,
            new NexusOperationContext(),
        ));

        self::assertSame(ErrorType::NotFound, $e->errorType);
        self::assertStringContainsString("Unrecognized service 'NonExistent'", $e->getMessage());
    }

    public function testUnrecognizedOperation(): void
    {
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new VoidService())],
        );

        $e = self::assertThrown(HandlerException::class, fn() => $handler->startOperation(
            new OperationContext(service: 'VoidServiceInterface', operation: 'nonExistent', env: $this->env),
            new OperationStartDetails(requestId: 'r1'),
            EncodedValues::empty(),
            null,
            new NexusOperationContext(),
        ));

        self::assertSame(ErrorType::NotFound, $e->errorType);
        self::assertStringContainsString("has no operation 'nonExistent'", $e->getMessage());
    }

    public function testCancelOnSyncHandlerThrowsNotImplemented(): void
    {
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new VoidService())],
        );

        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('synchronous and cannot be cancelled');
        $handler->cancelOperation(
            new OperationContext(service: 'VoidServiceInterface', operation: 'operation', env: $this->env),
            new OperationCancelDetails(operationToken: 'some-token'),
            null,
            new NexusOperationContext(),
        );
    }

    private static function newGreetingHandler(?PipelineProvider $interceptorProvider = null): ServiceHandler
    {
        $apiClient = static fn(string $name): string => "greeting-{$name}";

        return ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new GreetingService($apiClient))],
            interceptorProvider: $interceptorProvider ?? new SimplePipelineProvider(),
        );
    }

    private function newGreetingContext(string $operation): OperationContext
    {
        return new OperationContext(service: 'GreetingServiceInterface', operation: $operation, env: $this->env);
    }

    private function asyncOperationContext(): NexusOperationContext
    {
        return new NexusOperationContext(self::NS, self::TQ);
    }
}
