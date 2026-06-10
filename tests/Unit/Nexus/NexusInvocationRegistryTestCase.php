<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Nexus\Handler\MethodCanceller;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusInvocationRegistry::class)]
final class NexusInvocationRegistryTestCase extends AbstractUnit
{
    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
    }

    public function testGetReturnsNullForUnknownId(): void
    {
        $registry = new NexusInvocationRegistry();

        self::assertNull($registry->get(42));
    }

    public function testUnregisterRemovesEntry(): void
    {
        $registry = new NexusInvocationRegistry();
        $canceller = new MethodCanceller($this->env);
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
        $a = new MethodCanceller($this->env);
        $b = new MethodCanceller($this->env);
        $registry->register(1, $a);
        $registry->register(2, $b);

        self::assertSame($a, $registry->get(1));
        self::assertSame($b, $registry->get(2));
    }

    public function testCancelThroughRegistryAffectsOriginalCanceller(): void
    {
        $registry = new NexusInvocationRegistry();
        $canceller = new MethodCanceller($this->env);
        $registry->register(7, $canceller);

        $registry->get(7)?->cancel('deadline');

        self::assertTrue($canceller->isCancelled());
        self::assertSame('deadline', $canceller->getReason());
    }
}
