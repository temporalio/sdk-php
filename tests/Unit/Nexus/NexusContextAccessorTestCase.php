<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use PHPUnit\Framework\MockObject\MockObject;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Internal\Nexus\NexusContext;
use Temporal\Internal\Nexus\NexusEnvironment;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Nexus;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(Nexus::class)]
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
        $this->expectExceptionMessage('The Nexus facade can be used only inside a Nexus operation handler.');
        Nexus::getOperationContext();
    }

    public function testReturnsCurrentContext(): void
    {
        /** @var WorkflowClientInterface&MockObject $client */
        $client = $this->createMock(WorkflowClientInterface::class);
        $ctx = new NexusOperationContext('ns', 'tq');

        Nexus::setCurrentContext(new NexusContext(
            current: new OperationContext(service: 'svc', operation: 'op'),
            operation: $ctx,
            environment: new NexusEnvironment('ns', 'tq', $client),
        ));

        self::assertSame($ctx, Nexus::getOperationContext());
        self::assertSame('ns', Nexus::getOperationContext()->namespace);
        self::assertSame('tq', Nexus::getOperationContext()->taskQueue);
    }

    public function testPublicContextDoesNotExposeWorkflowClient(): void
    {
        $ctx = new NexusOperationContext('ns', 'tq');

        self::assertFalse(
            \property_exists($ctx, 'workflowClient'),
            'NexusOperationContext must not leak the WorkflowClient into public API surface',
        );
    }

    public function testClearingRestoresOutsideBehavior(): void
    {
        Nexus::setCurrentContext(new NexusContext(
            current: new OperationContext(service: 'svc', operation: 'op'),
            operation: new NexusOperationContext('ns', 'tq'),
        ));
        Nexus::setCurrentContext(null);

        $this->expectException(\LogicException::class);
        Nexus::getOperationContext();
    }
}
