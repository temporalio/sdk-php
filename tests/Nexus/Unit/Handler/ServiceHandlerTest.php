<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\HandlerInputContent;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\ServiceHandler;
use Temporal\Nexus\Handler\ServiceImplInstance;
use Temporal\Tests\Nexus\Fixture\Impl\GreetingServiceImpl;
use Temporal\Tests\Nexus\Fixture\Serializer\StringOnlySerializer;
use Temporal\Tests\Nexus\Fixture\ServiceHandler\AuthInterceptor;
use Temporal\Tests\Nexus\Fixture\ServiceHandler\LoggingInterceptor;
use Temporal\Tests\Nexus\Fixture\ServiceHandler\VoidServiceImpl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class ServiceHandlerTest extends TestCase
{
    public function testVoidService(): void
    {
        $serviceImpl = ServiceImplInstance::fromInstance(new VoidServiceImpl());
        self::assertCount(1, $serviceImpl->operationHandlers);
    }

    public function testSyncHandlerReturnsSyncResult(): void
    {
        $handler = self::newGreetingHandler();

        $result = $handler->startOperation(
            self::newGreetingContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('SomeUser'),
        );

        self::assertSame('Hello, SomeUser!', $result->value->data);
    }

    public function testAsyncHandlerReturnsToken(): void
    {
        $handler = self::newGreetingHandler();

        $result = $handler->startOperation(
            self::newGreetingContext('sayHello2'),
            new OperationStartDetails(requestId: 'r3'),
            new HandlerInputContent('SomeUser'),
        );

        $token = $result->info->token;
        self::assertNotNull($token);
        self::assertNotEmpty($token);
    }

    public function testAsyncHandlerCollectsLinksOnLinkSuffixedInput(): void
    {
        $handler = self::newGreetingHandler();
        $context = self::newGreetingContext('sayHello2');

        $result = $handler->startOperation(
            $context,
            new OperationStartDetails(requestId: 'r4'),
            new HandlerInputContent('SomeUser-link'),
        );

        self::assertNotNull($result->info->token);
        $links = $context->links->all();
        self::assertCount(1, $links);
        self::assertSame('http://somepath?k=v', $links[0]->uri);
        self::assertSame('com.example.MyResource', $links[0]->type);
    }

    public function testAuthInterceptorAllowsCallWithValidToken(): void
    {
        $token = 'auth-token';
        $handler = self::newGreetingHandler([
            new AuthInterceptor($token),
            new LoggingInterceptor(),
        ]);

        $result = $handler->startOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello1',
                headers: [AuthInterceptor::AUTH_HEADER => $token],
            ),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('SomeUser'),
        );

        self::assertSame('Hello, SomeUser!', $result->value->data);
    }

    public function testAuthInterceptorRejectsCallWithMissingToken(): void
    {
        $handler = self::newGreetingHandler([
            new AuthInterceptor('auth-token'),
            new LoggingInterceptor(),
        ]);

        $this->expectException(HandlerException::class);
        $handler->startOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello1',
            ),
            new OperationStartDetails(requestId: 'r2'),
            new HandlerInputContent('SomeUser'),
        );
    }

    public function testLoggingInterceptorRecordsAuthorizedCallsOnly(): void
    {
        $token = 'auth-token';
        $logger = new LoggingInterceptor();
        $handler = self::newGreetingHandler([
            new AuthInterceptor($token),
            $logger,
        ]);

        $handler->startOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello1',
                headers: [AuthInterceptor::AUTH_HEADER => $token],
            ),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('SomeUser'),
        );

        // Auth is before logging, so an unauthorized call never reaches the logger.
        self::assertSame(['sayHello1'], $logger->getOperations());
    }

    public function testUnrecognizedService(): void
    {
        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [ServiceImplInstance::fromInstance(new VoidServiceImpl())],
        );

        $this->expectException(HandlerException::class);
        $handler->startOperation(
            new OperationContext(service: 'NonExistent', operation: 'op'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent(''),
        );
    }

    public function testUnrecognizedOperation(): void
    {
        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [ServiceImplInstance::fromInstance(new VoidServiceImpl())],
        );

        $this->expectException(HandlerException::class);
        $handler->startOperation(
            new OperationContext(service: 'VoidServiceInterface', operation: 'nonExistent'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent(''),
        );
    }

    public function testCancelOnSyncHandlerThrowsNotImplemented(): void
    {
        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [ServiceImplInstance::fromInstance(new VoidServiceImpl())],
        );

        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('synchronous and cannot be cancelled');
        $handler->cancelOperation(
            new OperationContext(service: 'VoidServiceInterface', operation: 'operation'),
            new OperationCancelDetails(operationToken: 'some-token'),
        );
    }

    /**
     * @param list<\Temporal\Nexus\Handler\OperationMiddlewareInterface> $middlewares
     */
    private static function newGreetingHandler(array $middlewares = []): ServiceHandler
    {
        $apiClient = static fn(string $name): string => "greeting-{$name}";

        return ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [ServiceImplInstance::fromInstance(new GreetingServiceImpl($apiClient))],
            middlewares: $middlewares,
        );
    }

    private static function newGreetingContext(string $operation): OperationContext
    {
        return new OperationContext(service: 'GreetingServiceInterface', operation: $operation);
    }
}
