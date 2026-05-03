<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use PHPUnit\Framework\MockObject\MockObject;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Internal\Nexus\NexusContext;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
final class NexusContextAccessorTestCase extends AbstractUnit
{
    protected function tearDown(): void
    {
        // Always clear — leaking a context would poison sibling tests that
        // rely on `getOperationContext()` throwing outside a handler.
        Nexus::setCurrentContext(null);
    }

    public function testOutsideDispatchThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Nexus::getOperationContext()');
        Nexus::getOperationContext();
    }

    public function testReturnsCurrentContext(): void
    {
        /** @var WorkflowClientInterface&MockObject $client */
        $client = $this->createMock(WorkflowClientInterface::class);
        $ctx = new NexusOperationContext('ns', 'tq', $client);

        Nexus::setCurrentContext(new NexusContext(operation: $ctx));

        self::assertSame($ctx, Nexus::getOperationContext());
        self::assertSame('ns', Nexus::getOperationContext()->namespace);
        self::assertSame('tq', Nexus::getOperationContext()->taskQueue);
        self::assertSame($client, Nexus::getOperationContext()->workflowClient);
    }

    public function testClearingRestoresOutsideBehavior(): void
    {
        /** @var WorkflowClientInterface&MockObject $client */
        $client = $this->createMock(WorkflowClientInterface::class);

        Nexus::setCurrentContext(new NexusContext(operation: new NexusOperationContext('ns', 'tq', $client)));
        Nexus::setCurrentContext(null);

        $this->expectException(\LogicException::class);
        Nexus::getOperationContext();
    }
}
