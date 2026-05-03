<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Handler\Internal\MethodOperationHandler;
use Temporal\Nexus\Handler\Internal\ServiceImplFactory;
use Temporal\Nexus\Handler\Internal\ServiceImplInstance;
use Temporal\Tests\Nexus\Fixture\Service\GreetingServiceInterface;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ChildInheritingHandler;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\NoServiceImplAnnotation;
use Temporal\Tests\Nexus\Fixture\ServiceImplInstance\ServiceImplWithExtraNonOperationMethod;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceImplInstance::class)]
#[CoversClass(ServiceImplFactory::class)]
#[CoversClass(MethodOperationHandler::class)]
final class ServiceImplInstanceTest extends TestCase
{
    use ExceptionAssertions;

    public function testMissingContractInterfaceRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must implement an interface annotated with #\[.+Service\]/');
        ServiceImplInstance::fromInstance(new NoServiceImplAnnotation());
    }

    public function testInheritedHandlerIsDiscovered(): void
    {
        $impl = ServiceImplInstance::fromInstance(new ChildInheritingHandler());
        self::assertArrayHasKey('operation', $impl->operationHandlers);
    }

    public function testServiceImplWithExtraNonOperationMethodIsAccepted(): void
    {
        $instance = ServiceImplInstance::fromInstance(new ServiceImplWithExtraNonOperationMethod());

        self::assertCount(1, $instance->operationHandlers);
        self::assertArrayHasKey('operation', $instance->operationHandlers);
    }

    public function testCancelMethodTargetingUnknownOperationRejected(): void
    {
        $impl = new class implements GreetingServiceInterface {
            public function sayHello1(string $name): string
            {
                return '';
            }

            public function sayHello2(string $name): \Temporal\Nexus\OperationInfo
            {
                return new \Temporal\Nexus\OperationInfo('tok', \Temporal\Nexus\OperationState::Running);
            }

            #[\Temporal\Nexus\Attribute\OperationCancel(operation: 'doesNotExist')]
            public function strayCancel(string $token): void {}
        };

        $e = self::assertThrown(
            NexusException::class,
            static fn() => ServiceImplInstance::fromInstance($impl),
        );

        self::assertStringContainsString('targets unknown operation', $e->getMessage());
    }

    public function testMultipleCancelMethodsForSameOperationRejected(): void
    {
        $impl = new class implements GreetingServiceInterface {
            public function sayHello1(string $name): string
            {
                return '';
            }

            public function sayHello2(string $name): \Temporal\Nexus\OperationInfo
            {
                return new \Temporal\Nexus\OperationInfo('tok', \Temporal\Nexus\OperationState::Running);
            }

            #[\Temporal\Nexus\Attribute\OperationCancel(operation: 'sayHello2')]
            public function cancelOne(string $token): void {}

            #[\Temporal\Nexus\Attribute\OperationCancel(operation: 'sayHello2')]
            public function cancelTwo(string $token): void {}
        };

        $e = self::assertThrown(
            NexusException::class,
            static fn() => ServiceImplInstance::fromInstance($impl),
        );

        self::assertStringContainsString('Multiple #[', $e->getMessage());
    }

    public function testCancelMethodMustAcceptToken(): void
    {
        $impl = new class implements GreetingServiceInterface {
            public function sayHello1(string $name): string
            {
                return '';
            }

            public function sayHello2(string $name): \Temporal\Nexus\OperationInfo
            {
                return new \Temporal\Nexus\OperationInfo('tok', \Temporal\Nexus\OperationState::Running);
            }

            #[\Temporal\Nexus\Attribute\OperationCancel(operation: 'sayHello2')]
            public function badCancel(): void {}
        };

        $e = self::assertThrown(
            InvalidArgumentException::class,
            static fn() => ServiceImplInstance::fromInstance($impl),
        );

        self::assertStringContainsString('must accept the operation token', $e->getMessage());
    }
}
