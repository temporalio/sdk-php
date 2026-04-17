<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Nexus\Sdk\Handler\MethodCanceller;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
final class NexusInvocationRegistryTestCase extends AbstractUnit
{
    public function testGetReturnsNullForUnknownId(): void
    {
        $registry = new NexusInvocationRegistry();

        self::assertNull($registry->get(42));
    }

    public function testRegisterAndGet(): void
    {
        $registry = new NexusInvocationRegistry();
        $canceller = new MethodCanceller();

        $registry->register(1, $canceller);

        self::assertSame($canceller, $registry->get(1));
    }

    public function testUnregisterRemovesEntry(): void
    {
        $registry = new NexusInvocationRegistry();
        $canceller = new MethodCanceller();
        $registry->register(5, $canceller);

        $registry->unregister(5);

        self::assertNull($registry->get(5));
    }

    public function testUnregisterUnknownIdIsNoOp(): void
    {
        $registry = new NexusInvocationRegistry();

        // Must not throw.
        $registry->unregister(999);

        self::assertNull($registry->get(999));
    }

    public function testEntriesAreIsolatedByKey(): void
    {
        $registry = new NexusInvocationRegistry();
        $a = new MethodCanceller();
        $b = new MethodCanceller();
        $registry->register(1, $a);
        $registry->register(2, $b);

        self::assertSame($a, $registry->get(1));
        self::assertSame($b, $registry->get(2));
    }

    public function testCancelThroughRegistryAffectsOriginalCanceller(): void
    {
        $registry = new NexusInvocationRegistry();
        $canceller = new MethodCanceller();
        $registry->register(7, $canceller);

        $registry->get(7)?->cancel('deadline');

        self::assertTrue($canceller->isCancelled());
        self::assertSame('deadline', $canceller->getReason());
    }
}
