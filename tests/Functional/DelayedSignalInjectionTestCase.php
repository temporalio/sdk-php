<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Testing\WorkflowTestCase;

final class DelayedSignalInjectionTestCase extends WorkflowTestCase
{
    public function testSignalIsDeliveredAfterSimulatedDelay(): void
    {
        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'DelayedSignalWorkflow',
            WorkflowOptions::new()->withTaskQueue('default'),
        );

        $before = $this->testingService->getCurrentTime()->getTimestamp();
        $this->delayedCallbacks->signalAfter(30, 'unblock', 'hello')->start($stub, 120);
        $after = $this->testingService->getCurrentTime()->getTimestamp();

        self::assertGreaterThanOrEqual(30, $after - $before);
        self::assertSame('signal:hello', $stub->getResult('string', 30));
    }
}
