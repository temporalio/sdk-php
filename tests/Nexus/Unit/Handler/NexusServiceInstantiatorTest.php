<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Instantiator\NexusServiceInstantiator;
use Temporal\Internal\Declaration\NexusServiceInstance;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Handler\Internal\MethodOperationHandler;
use Temporal\Tests\Nexus\Fixtures\Service\GreetingServiceInterface;
use Temporal\Tests\Nexus\Fixtures\ServiceImplInstance\ChildInheritingHandler;
use Temporal\Tests\Nexus\Fixtures\ServiceImplInstance\NoServiceAnnotation;
use Temporal\Tests\Nexus\Fixtures\ServiceImplInstance\ServiceAsClass;
use Temporal\Tests\Nexus\Fixtures\ServiceImplInstance\ServiceWithExtraNonOperationMethod;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NexusServiceInstantiator::class)]
#[CoversClass(NexusServiceInstance::class)]
#[CoversClass(MethodOperationHandler::class)]
#[UsesClass(NexusServiceReader::class)]
#[UsesClass(NexusServicePrototype::class)]
final class NexusServiceInstantiatorTest extends TestCase
{
    use ExceptionAssertions;

    public function testMissingContractInterfaceRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Missing #\[Service\] attribute/');
        self::bind(new NoServiceAnnotation());
    }

    public function testServiceAttributeOnClassIsAccepted(): void
    {
        $instance = self::bind(new ServiceAsClass());

        self::assertSame('ServiceAsClass', $instance->prototype->getID());
        self::assertCount(1, $instance->operationHandlers);
        self::assertArrayHasKey('classOperation', $instance->operationHandlers);
    }

    public function testInheritedHandlerIsDiscovered(): void
    {
        $instance = self::bind(new ChildInheritingHandler());
        self::assertArrayHasKey('operation', $instance->operationHandlers);
    }

    public function testServiceWithExtraNonOperationMethodIsAccepted(): void
    {
        $instance = self::bind(new ServiceWithExtraNonOperationMethod());

        self::assertCount(1, $instance->operationHandlers);
        self::assertArrayHasKey('operation', $instance->operationHandlers);
    }

    public function testCancelMethodTargetingUnknownOperationRejected(): void
    {
        $instance = new class implements GreetingServiceInterface {
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
            static fn() => self::bind($instance),
        );

        self::assertStringContainsString('targets unknown operation', $e->getMessage());
    }

    public function testMultipleCancelMethodsForSameOperationRejected(): void
    {
        $instance = new class implements GreetingServiceInterface {
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
            static fn() => self::bind($instance),
        );

        self::assertStringContainsString('Multiple #[', $e->getMessage());
    }

    public function testCancelMethodTargetingSyncOperationRejectedAtRegistration(): void
    {
        $instance = new class implements GreetingServiceInterface {
            public function sayHello1(string $name): string
            {
                return '';
            }

            public function sayHello2(string $name): \Temporal\Nexus\OperationInfo
            {
                return new \Temporal\Nexus\OperationInfo('tok', \Temporal\Nexus\OperationState::Running);
            }

            // Stray cancel on a sync operation — sync ops have no terminal-state
            // cancellation, so binding a cancel routine to one is always wrong.
            #[\Temporal\Nexus\Attribute\OperationCancel(operation: 'sayHello1')]
            public function cancelSync(string $token): void {}
        };

        $e = self::assertThrown(
            NexusException::class,
            static fn() => self::bind($instance),
        );

        self::assertStringContainsString('targets a synchronous operation', $e->getMessage());
        self::assertStringContainsString('sayHello1', $e->getMessage());
    }

    public function testCancelMethodMustAcceptToken(): void
    {
        $instance = new class implements GreetingServiceInterface {
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
            static fn() => self::bind($instance),
        );

        self::assertStringContainsString('must accept the operation token', $e->getMessage());
    }

    /**
     * Helper that wires Reader+Instantiator together — the same flow the
     * Worker uses at registration time.
     */
    private static function bind(object $instance): NexusServiceInstance
    {
        $reader = new NexusServiceReader(new AttributeReader());
        $prototype = $reader->fromClass(\get_class($instance))->withInstance($instance);
        return (new NexusServiceInstantiator())->instantiate($prototype);
    }
}
