<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Testing\DelayedCallbackScheduler;
use Temporal\Testing\TimeSkippingWorkflowTestCase;
use Temporal\Testing\WorkflowTestCase;

final class NestedTimeSkipGuardTestCase extends TimeSkippingWorkflowTestCase
{
    public function testSchedulerRefusesToNestInsideALockedContext(): void
    {
        $this->delayedCallbacks->registerDelayedCallback(1, static fn(WorkflowStubInterface $s) => null);

        $stub = $this->workflowClient->newUntypedWorkflowStub(
            'SignalCollectorWorkflow',
            WorkflowOptions::new()->withTaskQueue('default'),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf(
            '%s must own time-skipping exclusively, but the lock counter is already held (delta 1). '
            . 'Do not drive it inside %s or alongside another time-skip driver; extend the plain %s instead.',
            DelayedCallbackScheduler::class,
            TimeSkippingWorkflowTestCase::class,
            WorkflowTestCase::class,
        ));

        $this->delayedCallbacks->start($stub, 3600);
    }
}
