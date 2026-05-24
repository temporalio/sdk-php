<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\Fibers\Basic;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/*

# Basic activity

The most basic workflow which just runs an activity and returns its result.
Importantly, without setting a workflow execution timeout.

# Detailed spec

It's important that the workflow execution timeout is not set here, because server will propagate that to all un-set
activity timeouts. We had a bug where TS would crash (after proto changes from gogo to google) because it was expecting
timeouts to be set to zero rather than null.

*/

class BasicTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Harness_Activity_Fibers_Basic')]WorkflowStubInterface $stub): void
    {
        self::assertSame('echo', $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_Activity_Fibers_Basic')]
    public function run()
    {
        Workflow::newActivityStub(
            FeatureActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout('1 minute'),
        )->echo();

        return Workflow::newActivityStub(
            FeatureActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout('1 minute'),
        )->echo();
    }
}

#[ActivityInterface(prefix: 'Fibers_')]
class FeatureActivity
{
    #[ActivityMethod('echo')]
    public function echo(): string
    {
        return 'echo';
    }
}
