<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityPaused;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Enums\V1\PendingActivityState;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\PauseActivityRequest;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\ActivityPausedException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ActivityPausedTest extends TestCase
{
    #[Test]
    public function simplePause(
        #[Stub('Extra_Activity_ActivityPaused', executionTimeout: '200 seconds')] WorkflowStubInterface $stub,
        ServiceClientInterface $serviceClient,
    ): void {
        $request = (new DescribeWorkflowExecutionRequest())
            ->setNamespace('default')
            ->setExecution(
                (new WorkflowExecution())
                    ->setWorkflowId($stub->getExecution()->getID())
                    ->setRunId($stub->getExecution()->getRunID()),
            );

        $deadline = \microtime(true) + 30;
        $activityId = null;
        while (\microtime(true) < $deadline) {
            $description = $serviceClient->DescribeWorkflowExecution($request);
            foreach ($description->getPendingActivities() as $pendingActivity) {
                if (
                    $pendingActivity->getActivityType()?->getName() !== 'Extra_Activity_ActivityPaused.sleep'
                    || $pendingActivity->getState() !== PendingActivityState::PENDING_ACTIVITY_STATE_STARTED
                ) {
                    continue;
                }

                $activityId = $pendingActivity->getActivityId();
                break 2;
            }

            \usleep(100_000);
        }

        self::assertNotNull($activityId, 'Started activity not found for workflow execution');

        $serviceClient->PauseActivity(
            (new PauseActivityRequest())
                ->setReason('test')
                ->setNamespace('default')
                ->setId($activityId)
                ->setExecution($request->getExecution()),
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
            Activity\ActivityOptions::new()
                ->withScheduleToCloseTimeout('101 seconds')
                ->withHeartbeatTimeout('5 seconds'),
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

#[Activity\ActivityInterface(prefix: 'Extra_Activity_ActivityPaused.')]
class TestActivity
{
    #[Activity\ActivityMethod]
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
