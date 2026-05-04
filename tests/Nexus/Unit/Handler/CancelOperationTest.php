<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\Internal\HandlerInputContent;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Handler\Internal\ServiceImplInstance;
use Temporal\Tests\Nexus\Fixture\Impl\GreetingServiceImpl;
use Temporal\Tests\Nexus\Fixture\Serializer\StringOnlySerializer;
use Temporal\Tests\Nexus\Fixture\ServiceHandler\AuthInterceptor;
use Temporal\Tests\Nexus\Fixture\ServiceHandler\LoggingInterceptor;
use Temporal\Tests\Nexus\Fixture\ServiceHandler\VoidServiceImpl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class CancelOperationTest extends TestCase
{
    public function testCancelUnrecognizedService(): void
    {
        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [
                ServiceImplInstance::fromInstance(new VoidServiceImpl()),
            ],
        );

        $this->expectException(HandlerException::class);
        $handler->cancelOperation(
            new OperationContext(service: 'NonExistent', operation: 'op'),
            new OperationCancelDetails(operationToken: 'token'),
        );
    }

    public function testCancelUnrecognizedOperation(): void
    {
        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [
                ServiceImplInstance::fromInstance(new VoidServiceImpl()),
            ],
        );

        $this->expectException(HandlerException::class);
        $handler->cancelOperation(
            new OperationContext(service: 'VoidServiceInterface', operation: 'nonExistent'),
            new OperationCancelDetails(operationToken: 'token'),
        );
    }

    public function testCancelWithInterceptor(): void
    {
        $apiClient = fn(string $name): string => "greeting-{$name}";
        $authToken = 'auth-token';
        $loggingInterceptor = new LoggingInterceptor();

        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [
                ServiceImplInstance::fromInstance(new GreetingServiceImpl($apiClient)),
            ],
            interceptorProvider: new SimplePipelineProvider([
                new AuthInterceptor($authToken),
                $loggingInterceptor,
            ]),
        );

        // Start an async operation first.
        $result = $handler->startOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello2',
                headers: [AuthInterceptor::AUTH_HEADER => $authToken],
            ),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('SomeUser'),
        );

        $token = $result->info->token;
        self::assertNotNull($token);

        // Cancel it.
        $handler->cancelOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello2',
                headers: [AuthInterceptor::AUTH_HEADER => $authToken],
            ),
            new OperationCancelDetails(operationToken: $token),
        );

        // Logging interceptor saw both start and cancel.
        self::assertSame(['sayHello2', 'sayHello2'], $loggingInterceptor->getOperations());
    }
}
