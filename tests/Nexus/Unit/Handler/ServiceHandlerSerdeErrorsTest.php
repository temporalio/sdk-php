<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Exception\RetryBehavior;
use Temporal\Nexus\Handler\Internal\HandlerInputContent;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Handler\Internal\ServiceImplInstance;
use Temporal\Tests\Nexus\Fixture\Serializer\EchoSerializer;
use Temporal\Tests\Nexus\Fixture\Serializer\FailingDeserializer;
use Temporal\Tests\Nexus\Fixture\Serializer\FailingSerializer;
use Temporal\Tests\Nexus\Fixture\Impl\ThrowingGreetingImpl;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class ServiceHandlerSerdeErrorsTest extends TestCase
{
    use ExceptionAssertions;

    public function testDeserializeFailureWrapsAsBadRequestHandlerException(): void
    {
        $handler = self::newHandler(new FailingDeserializer());

        $e = self::assertThrown(HandlerException::class, static fn() => $handler->startOperation(
            self::newContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('garbage'),
        ));

        self::assertSame(ErrorType::BadRequest, $e->errorType);
        self::assertMatchesRegularExpression(
            '#Failed deserializing input for GreetingServiceInterface/sayHello1 as string: Bad JSON#',
            $e->getMessage(),
        );
        self::assertInstanceOf(\JsonException::class, $e->getPrevious());
    }

    public function testSerializeFailureWrapsAsInternalHandlerException(): void
    {
        $handler = self::newHandler(new FailingSerializer());

        $e = self::assertThrown(HandlerException::class, static fn() => $handler->startOperation(
            self::newContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('anything'),
        ));

        self::assertSame(ErrorType::Internal, $e->errorType);
        self::assertMatchesRegularExpression(
            '#Failed serializing result for GreetingServiceInterface/sayHello1 as string: cannot serialize#',
            $e->getMessage(),
        );
        self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
    }

    public function testOperationExceptionFromHandlerIsNotWrapped(): void
    {
        $handler = self::newHandler(
            new EchoSerializer(),
            new ThrowingGreetingImpl(hello1Throw: OperationException::failed('intentional business failure')),
        );

        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('intentional business failure');
        $handler->startOperation(
            self::newContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('anything'),
        );
    }

    public function testHandlerExceptionFromHandlerIsNotDoubleWrapped(): void
    {
        $handler = self::newHandler(
            new EchoSerializer(),
            new ThrowingGreetingImpl(hello1Throw: HandlerException::create(
                ErrorType::Unauthorized,
                'no auth',
                retryBehavior: RetryBehavior::NonRetryable,
            )),
        );

        $e = self::assertThrown(HandlerException::class, static fn() => $handler->startOperation(
            self::newContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('anything'),
        ));

        self::assertSame(ErrorType::Unauthorized, $e->errorType);
        self::assertSame('no auth', $e->getMessage());
        self::assertSame(RetryBehavior::NonRetryable, $e->retryBehavior);
    }

    public function testDeserializeErrorMessageContainsServiceOperationAndType(): void
    {
        $handler = self::newHandler(new FailingDeserializer());

        $e = self::assertThrown(HandlerException::class, static fn() => $handler->startOperation(
            self::newContext('sayHello2'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('garbage'),
        ));

        self::assertStringContainsString('GreetingServiceInterface', $e->getMessage());
        self::assertStringContainsString('sayHello2', $e->getMessage());
        self::assertStringContainsString('string', $e->getMessage());
    }

    public function testSerializeErrorMessageContainsOutputType(): void
    {
        $handler = self::newHandler(new FailingSerializer());

        $e = self::assertThrown(HandlerException::class, static fn() => $handler->startOperation(
            self::newContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('anything'),
        ));

        self::assertStringContainsString('as string', $e->getMessage());
    }

    private static function newHandler(
        \Temporal\Nexus\Serializer\Internal\SerializerInterface $serializer,
        ?ThrowingGreetingImpl $impl = null,
    ): ServiceHandler {
        return ServiceHandler::create(
            serializer: $serializer,
            instances: [ServiceImplInstance::fromInstance($impl ?? new ThrowingGreetingImpl())],
        );
    }

    private static function newContext(string $operation): OperationContext
    {
        return new OperationContext(
            service: 'GreetingServiceInterface',
            operation: $operation,
        );
    }
}
