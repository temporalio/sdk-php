<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityPaused;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Client\WorkflowStubInterface;
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
    ): void {
        $this->markTestSkipped('We can not pause the activity in the test');

        $stub->getResult(timeout: 200);
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Activity_ActivityPaused")]
    public function handle()
    {
        $stub = Workflow::newUntypedActivityStub(
            Activity\ActivityOptions::new()->withScheduleToCloseTimeout('101 seconds')
        );

        $run = $stub->execute('Extra_Activity_ActivityPaused.sleep', args: [100]);

        yield Workflow::timer('500 milliseconds');

        // todo: pause using some API?

        // $run->

        yield $run;

        return 'done';
    }
}

#[Activity\ActivityInterface(prefix: 'Extra_Activity_ActivityPaused.')]
class TestActivity
{
    #[Activity\ActivityMethod]
    public function sleep(int $seconds): void
    {
        $start = \microtime(true);
        $deadline = $start + (float) $seconds;
        while (\microtime(true) < $deadline) {
            \usleep(100);
            try {
                Activity::heartbeat(\sprintf('%d seconds left', $deadline - \microtime(true)));
            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }
}
