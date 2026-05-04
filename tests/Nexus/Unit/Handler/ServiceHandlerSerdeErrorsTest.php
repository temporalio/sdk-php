<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Exception\RetryBehavior;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Handler\Internal\ServiceImplInstance;
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
        $converter = self::failingDeserializeConverter();
        $handler = self::newHandler($converter);

        $this->expectException(HandlerException::class);
        $this->expectExceptionMessageMatches(
            '#Failed deserializing input for GreetingServiceInterface/sayHello1 as string: Bad JSON#',
        );

        try {
            $handler->startOperation(
                self::newContext('sayHello1'),
                new OperationStartDetails(requestId: 'r1'),
                self::input($converter),
            );
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::BadRequest, $e->errorType);
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
            throw $e;
        }
    }

    public function testSerializeFailureWrapsAsInternalHandlerException(): void
    {
        $converter = self::failingSerializeConverter();
        $handler = self::newHandler($converter);

        $this->expectException(HandlerException::class);
        $this->expectExceptionMessageMatches(
            '#Failed serializing result for GreetingServiceInterface/sayHello1 as string: cannot serialize#',
        );

        try {
            $handler->startOperation(
                self::newContext('sayHello1'),
                new OperationStartDetails(requestId: 'r1'),
                self::input($converter),
            );
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::Internal, $e->errorType);
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            throw $e;
        }
    }

    public function testOperationExceptionFromHandlerIsNotWrapped(): void
    {
        $converter = DataConverter::createDefault();
        $handler = self::newHandler(
            $converter,
            new ThrowingGreetingImpl(hello1Throw: OperationException::failed('intentional business failure')),
        );

        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('intentional business failure');
        $handler->startOperation(
            self::newContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            EncodedValues::fromValues(['anything'], $converter),
        );
    }

    public function testHandlerExceptionFromHandlerIsNotDoubleWrapped(): void
    {
        $converter = DataConverter::createDefault();
        $handler = self::newHandler(
            $converter,
            new ThrowingGreetingImpl(hello1Throw: HandlerException::create(
                ErrorType::Unauthorized,
                'no auth',
                retryBehavior: RetryBehavior::NonRetryable,
            )),
        );

        $e = self::assertThrown(HandlerException::class, static fn() => $handler->startOperation(
            self::newContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            EncodedValues::fromValues(['anything'], $converter),
        ));

        self::assertSame(ErrorType::Unauthorized, $e->errorType);
        self::assertSame('no auth', $e->getMessage());
        self::assertSame(RetryBehavior::NonRetryable, $e->retryBehavior);
    }

    public function testDeserializeErrorMessageContainsServiceOperationAndType(): void
    {
        $converter = self::failingDeserializeConverter();
        $handler = self::newHandler($converter);

        $e = self::assertThrown(HandlerException::class, static fn() => $handler->startOperation(
            self::newContext('sayHello2'),
            new OperationStartDetails(requestId: 'r1'),
            self::input($converter),
        ));

        self::assertStringContainsString('GreetingServiceInterface', $e->getMessage());
        self::assertStringContainsString('sayHello2', $e->getMessage());
        self::assertStringContainsString('string', $e->getMessage());
    }

    public function testSerializeErrorMessageContainsOutputType(): void
    {
        $converter = self::failingSerializeConverter();
        $handler = self::newHandler($converter);

        $e = self::assertThrown(HandlerException::class, static fn() => $handler->startOperation(
            self::newContext('sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            self::input($converter),
        ));

        self::assertStringContainsString('as string', $e->getMessage());
    }

    private static function newHandler(
        DataConverterInterface $dataConverter,
        ?ThrowingGreetingImpl $impl = null,
    ): ServiceHandler {
        return ServiceHandler::create(
            dataConverter: $dataConverter,
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

    /**
     * Wrap a single dummy proto Payload so ServiceHandler routes through
     * `DataConverterInterface::fromPayload()` (the path that may raise).
     */
    private static function input(DataConverterInterface $dataConverter): EncodedValues
    {
        $payload = new Payload();
        $payload->setData('garbage');
        $payloads = new \Temporal\Api\Common\V1\Payloads(['payloads' => [$payload]]);
        return EncodedValues::fromPayloads($payloads, $dataConverter);
    }

    private static function failingDeserializeConverter(): DataConverterInterface
    {
        return new class implements DataConverterInterface {
            public function fromPayload(Payload $payload, mixed $type): mixed
            {
                throw new \JsonException('Bad JSON');
            }

            public function toPayload(mixed $value): Payload
            {
                $p = new Payload();
                $p->setData((string) $value);
                return $p;
            }
        };
    }

    private static function failingSerializeConverter(): DataConverterInterface
    {
        return new class implements DataConverterInterface {
            public function fromPayload(Payload $payload, mixed $type): mixed
            {
                return $payload->getData();
            }

            public function toPayload(mixed $value): Payload
            {
                throw new \RuntimeException('cannot serialize');
            }
        };
    }
}
