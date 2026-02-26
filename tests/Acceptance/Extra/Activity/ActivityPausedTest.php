<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityPaused;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Workflowservice\V1\PauseActivityRequest;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\ActivityPausedException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;

class ActivityPausedTest extends TestCase
{
    #[Test]
    public function simplePause(
        #[Stub('Extra_Activity_ActivityPaused', executionTimeout: '200 seconds')] WorkflowStubInterface $stub,
        ServiceClientInterface $serviceClient,
        WorkflowClientInterface $workflowClient,
    ): void {
        $deadline = \microtime(true) + 10;
        find:
        $found = false;
        foreach ($workflowClient->getWorkflowHistory($stub->getExecution()) as $event) {
            if ($event->hasActivityTaskScheduledEventAttributes()) {
                $found = true;
                break;
            }
        }

        if (!$found && \microtime(true) < $deadline) {
            goto find;
        }

        self::assertTrue($found, '`Activity task started` event not found in workflow history');

        $serviceClient->PauseActivity(
            (new PauseActivityRequest())
                ->setReason('test')
                ->setNamespace('default')
                ->setType('Extra_Activity_ActivityPaused.sleep')
                ->setExecution(
                    (new WorkflowExecution())
                        ->setWorkflowId($stub->getExecution()->getID())
                        ->setRunId($stub->getExecution()->getRunID()),
                ),
        );
        $result = $stub->getResult(timeout: 200);

        self::assertSame(ActivityPausedException::class, $result);
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Activity_ActivityPaused")]
    public function handle()
    {
        $stub = Workflow::newUntypedActivityStub(
            ActivityOptions::new()->withScheduleToCloseTimeout('101 seconds'),
        );

        /** @see TestActivity::sleep() */
        $run = $stub->execute('Extra_Activity_ActivityPaused.sleep', args: [100]);

        $timerFired = ! yield Workflow::awaitWithTimeout(
            '20 seconds',
            $run,
        );

        return $timerFired ? 'timeout' : yield $run;
    }
}

#[ActivityInterface(prefix: 'Extra_Activity_ActivityPaused.')]
class TestActivity
{
    #[ActivityMethod]
    public function sleep(int $seconds): string
    {
        $start = \microtime(true);
        $deadline = $start + (float) $seconds;
        while (\microtime(true) < $deadline) {
            \usleep(50);
            try {
                Activity::heartbeat(\sprintf('%d seconds left', $deadline - \microtime(true)));
            } catch (\Throwable $e) {
                return $e::class;
            }
        }

        return 'done';
    }
}
