<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\ServiceImpl;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Handler\Internal\ClosureTypeValidator;
use Temporal\Nexus\Handler\Internal\OperationImplMethodValidator;
use Temporal\Nexus\Handler\Internal\ServiceImplFactory;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\ServiceImplInstance;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
use Temporal\Tests\Nexus\Fixture\Service\GenericServiceInterface;
use Temporal\Tests\Nexus\Fixture\Service\GreetingServiceInterface;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ChildInheritingHandler;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\CustomOperationHandler;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\NoServiceImplAnnotation;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplReturningNonHandler;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplPointingAtInvalidServiceClass;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithExtraNonOperationMethod;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithFunctorHandler;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithMismatchedMethodName;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithMissingReturnType;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithoutAnyOperationImpl;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithParametersOnHandler;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithPrivateHandler;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithStaticHandler;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceImplInstance::class)]
#[CoversClass(ServiceImplFactory::class)]
#[CoversClass(OperationImplMethodValidator::class)]
#[CoversClass(ClosureTypeValidator::class)]
final class ServiceImplInstanceTest extends TestCase
{
    use ExceptionAssertions;

    public function testMissingServiceImplAnnotation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing #[ServiceImpl] attribute on');
        ServiceImplInstance::fromInstance(new NoServiceImplAnnotation());
    }

    public function testPrivateHandlerRejected(): void
    {
        $this->assertWrappedReason(
            new ServiceImplWithPrivateHandler(),
            '/must be public/',
        );
    }

    public function testStaticHandlerRejected(): void
    {
        $this->assertWrappedReason(
            new ServiceImplWithStaticHandler(),
            '/cannot be static/',
        );
    }

    public function testHandlerWithParametersRejected(): void
    {
        $this->assertWrappedReason(
            new ServiceImplWithParametersOnHandler(),
            '/cannot have any parameters/',
        );
    }

    public function testMethodNameNotMatchingInterfaceRejected(): void
    {
        $this->assertWrappedReason(
            new ServiceImplWithMismatchedMethodName(),
            '/No matching #\[Operation\] declaration for method .+::notOnInterface\(\) in service interface/',
        );
    }

    public function testReturnTypeDeclaredMixedRejected(): void
    {
        $this->assertWrappedReason(
            new ServiceImplReturningNonHandler(),
            '/must return .+OperationHandlerInterface, got mixed/',
        );
    }

    public function testReturnTypeMissingRejected(): void
    {
        $this->assertWrappedReason(
            new ServiceImplWithMissingReturnType(),
            '/must return .+OperationHandlerInterface, got no declared type/',
        );
    }

    public function testSubtypeReturnTypeAccepted(): void
    {
        $subtyped = new #[ServiceImpl(service: GenericServiceInterface::class)] class {
            #[OperationImpl]
            public function operation(): SynchronousOperationHandler
            {
                return new SynchronousOperationHandler(static fn($ctx, $details, $name) => 'v');
            }
        };

        $impl = ServiceImplInstance::fromInstance($subtyped);
        self::assertArrayHasKey('operation', $impl->operationHandlers);
    }

    public function testInheritedHandlerIsDiscovered(): void
    {
        $impl = ServiceImplInstance::fromInstance(new ChildInheritingHandler());
        self::assertArrayHasKey('operation', $impl->operationHandlers);
    }

    public function testMissingHandlerForInterfaceOperationRejected(): void
    {
        $partialImpl = new #[ServiceImpl(service: GreetingServiceInterface::class)] class {
            #[OperationImpl]
            public function sayHello1(): OperationHandlerInterface
            {
                return new SynchronousOperationHandler(static fn($ctx, $details, $name) => 'only');
            }
        };

        $this->expectException(NexusException::class);
        $this->expectExceptionMessageMatches('/Missing handlers for service operations on .+: sayHello2/');
        ServiceImplInstance::fromInstance($partialImpl);
    }

    public function testClosureWithMatchingTypesAccepted(): void
    {
        // Interface declares `operation(string): string`; closure matches.
        $impl = new #[ServiceImpl(service: GenericServiceInterface::class)] class {
            #[OperationImpl]
            public function operation(): OperationHandlerInterface
            {
                return new SynchronousOperationHandler(
                    static fn($ctx, $details, ?string $name): string => (string) $name,
                );
            }
        };

        $inst = ServiceImplInstance::fromInstance($impl);
        self::assertArrayHasKey('operation', $inst->operationHandlers);
    }

    public function testClosureWithMismatchedInputRejected(): void
    {
        $impl = new #[ServiceImpl(service: GenericServiceInterface::class)] class {
            #[OperationImpl]
            public function operation(): OperationHandlerInterface
            {
                return new SynchronousOperationHandler(
                    static fn($ctx, $details, ?int $name): string => '',
                );
            }
        };

        $this->assertWrappedReason(
            $impl,
            '/handler input type "int" does not match operation "operation" declared input "string"/',
        );
    }

    public function testClosureWithMismatchedOutputRejected(): void
    {
        $impl = new #[ServiceImpl(service: GenericServiceInterface::class)] class {
            #[OperationImpl]
            public function operation(): OperationHandlerInterface
            {
                return new SynchronousOperationHandler(
                    static fn($ctx, $details, ?string $name): int => 0,
                );
            }
        };

        $this->assertWrappedReason(
            $impl,
            '/handler return type "int" does not match operation "operation" declared output "string"/',
        );
    }

    public function testClosureWithoutTypeHintsAcceptedAsWildcard(): void
    {
        $impl = new #[ServiceImpl(service: GenericServiceInterface::class)] class {
            #[OperationImpl]
            public function operation(): OperationHandlerInterface
            {
                return new SynchronousOperationHandler(static fn($ctx, $details, $name) => 'ok');
            }
        };

        $inst = ServiceImplInstance::fromInstance($impl);
        self::assertArrayHasKey('operation', $inst->operationHandlers);
    }

    public function testCustomHandlerClassSkipsTypeValidation(): void
    {
        $impl = new #[ServiceImpl(service: GenericServiceInterface::class)] class {
            #[OperationImpl]
            public function operation(): OperationHandlerInterface
            {
                return new CustomOperationHandler();
            }
        };

        $inst = ServiceImplInstance::fromInstance($impl);
        self::assertArrayHasKey('operation', $inst->operationHandlers);
    }

    public function testWrapperMessageIncludesOffendingMethod(): void
    {
        $e = self::assertThrown(
            NexusException::class,
            static fn() => ServiceImplInstance::fromInstance(new ServiceImplWithPrivateHandler()),
        );

        self::assertStringContainsString(
            'ServiceImplWithPrivateHandler::operation()',
            $e->getMessage(),
        );
    }

    public function testServiceImplPointingAtInvalidServiceClassWraps(): void
    {
        $e = self::assertThrown(
            NexusException::class,
            static fn() => ServiceImplInstance::fromInstance(new ServiceImplPointingAtInvalidServiceClass()),
        );

        self::assertStringContainsString('Failed loading #[ServiceImpl] class', $e->getMessage());
        self::assertNotNull($e->getPrevious());
    }

    public function testServiceImplWithoutAnyOperationImplRejected(): void
    {
        $e = self::assertThrown(
            NexusException::class,
            static fn() => ServiceImplInstance::fromInstance(new ServiceImplWithoutAnyOperationImpl()),
        );

        self::assertStringContainsString('No operation handlers defined', $e->getMessage());
    }

    public function testServiceImplWithExtraNonOperationMethodIsAccepted(): void
    {
        $instance = ServiceImplInstance::fromInstance(new ServiceImplWithExtraNonOperationMethod());

        self::assertCount(1, $instance->operationHandlers);
        self::assertArrayHasKey('operation', $instance->operationHandlers);
    }

    public function testFunctorHandlerSkipsClosureValidation(): void
    {
        $instance = ServiceImplInstance::fromInstance(new ServiceImplWithFunctorHandler());

        self::assertCount(2, $instance->operationHandlers);
    }

    /**
     * The wrapper message contains the method that failed; the actual reason is in the cause.
     */
    private function assertWrappedReason(object $instance, string $regexForCause): void
    {
        $e = self::assertThrown(
            NexusException::class,
            static fn() => ServiceImplInstance::fromInstance($instance),
        );

        self::assertMatchesRegularExpression('/Failed obtaining operation handler from /', $e->getMessage());
        $cause = $e->getPrevious();
        self::assertNotNull($cause, 'expected a cause');
        self::assertMatchesRegularExpression($regexForCause, $cause->getMessage());
    }
}
