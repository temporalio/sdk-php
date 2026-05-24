<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Experiments\Fibers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Experiments\Fibers\Mutex;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\Mutex as BaseMutex;

#[CoversClass(Workflow::class)]
final class WorkflowTestCase extends TestCase
{
    public function testUnwrapConditionsReplacesFiberMutexWithInner(): void
    {
        $fiberMutex = new Mutex();
        $baseMutex = new BaseMutex();
        $callable = static fn(): bool => true;

        $method = new \ReflectionMethod(Workflow::class, 'unwrapConditions');
        $unwrapped = $method->invoke(null, [$fiberMutex, $baseMutex, $callable]);

        self::assertCount(3, $unwrapped);
        self::assertSame($fiberMutex->getInner(), $unwrapped[0]);
        self::assertSame($baseMutex, $unwrapped[1]);
        self::assertSame($callable, $unwrapped[2]);
    }

    public function testUnwrapConditionsReturnsEmptyArrayForNoInput(): void
    {
        $method = new \ReflectionMethod(Workflow::class, 'unwrapConditions');
        $unwrapped = $method->invoke(null, []);

        self::assertSame([], $unwrapped);
    }

    public function testBaseAwaitSignatureDoesNotAcceptFiberMutex(): void
    {
        $parameter = (new \ReflectionMethod(\Temporal\Workflow::class, 'await'))->getParameters()[0];
        $type = $parameter->getType();

        self::assertInstanceOf(\ReflectionUnionType::class, $type);

        $names = \array_map(
            static fn(\ReflectionNamedType $t): string => $t->getName(),
            $type->getTypes(),
        );

        self::assertNotContains(Mutex::class, $names);
    }

    public function testBaseAwaitWithTimeoutSignatureDoesNotAcceptFiberMutex(): void
    {
        $parameter = (new \ReflectionMethod(\Temporal\Workflow::class, 'awaitWithTimeout'))->getParameters()[1];
        $type = $parameter->getType();

        self::assertInstanceOf(\ReflectionUnionType::class, $type);

        $names = \array_map(
            static fn(\ReflectionNamedType $t): string => $t->getName(),
            $type->getTypes(),
        );

        self::assertNotContains(Mutex::class, $names);
    }

    public function testFiberAwaitSignatureAcceptsFiberMutex(): void
    {
        $parameter = (new \ReflectionMethod(Workflow::class, 'await'))->getParameters()[0];
        $type = $parameter->getType();

        self::assertInstanceOf(\ReflectionUnionType::class, $type);

        $names = \array_map(
            static fn(\ReflectionNamedType $t): string => $t->getName(),
            $type->getTypes(),
        );

        self::assertContains(Mutex::class, $names);
    }
}
