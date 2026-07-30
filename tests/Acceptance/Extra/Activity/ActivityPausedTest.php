<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityPaused;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Api\Common\V1\WorkflowExecution;
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
        #[Stub('Extra_Activity_ActivityPaused', executionTimeout: '10 seconds')] WorkflowStubInterface $stub,
        ServiceClientInterface $serviceClient,
    ): void {
        $deadline = \microtime(true) + 5;
        $started = false;
        while (\microtime(true) < $deadline) {
            foreach ($stub->describe()->pendingActivities as $pending) {
                if ($pending->lastStartedTime !== null) {
                    $started = true;
                    break 2;
                }
            }
            \usleep(50_000);
        }

        self::assertTrue($started, 'Activity did not reach STARTED state in pending_activities');

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
        $result = $stub->getResult(timeout: 10);

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
                ->withScheduleToCloseTimeout('10 seconds')
                ->withHeartbeatTimeout('1 second'),
        );

        /** @see TestActivity::sleep() */
        $run = $stub->execute('Extra_Activity_ActivityPaused.sleep', args: [10]);

        $timerFired = ! yield Workflow::awaitWithTimeout(
            '10 seconds',
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
