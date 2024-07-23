<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\Basic;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
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
    public static function check(#[Stub('Workflow')] WorkflowStubInterface $stub): void
    {
        self::assertSame('echo', $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Workflow')]
    public function run()
    {
        yield Workflow::newActivityStub(
            FeatureActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout('1 minute'),
        )->echo();

        return yield Workflow::newActivityStub(
            FeatureActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout('1 minute'),
        )->echo();
    }
}

#[ActivityInterface]
class FeatureActivity
{
    #[ActivityMethod('echo')]
    public function echo(): string
    {
        return 'echo';
    }
}
