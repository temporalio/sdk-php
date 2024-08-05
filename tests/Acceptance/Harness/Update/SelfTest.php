<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\Self;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class SelfTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('HarnessWorkflow_Update_Self')]WorkflowStubInterface $stub,
    ): void {
        self::assertSame('Hello, world!', $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $done = false;

    #[WorkflowMethod('HarnessWorkflow_Update_Self')]
    public function run()
    {
        yield Workflow::executeActivity(
            'result',
            options: ActivityOptions::new()->withStartToCloseTimeout(2)
        );

        yield Workflow::await(fn(): bool => $this->done);

        return 'Hello, world!';
    }

    #[Workflow\UpdateMethod('my_update')]
    public function myUpdate()
    {
        $this->done = true;
    }
}

#[ActivityInterface]
class FeatureActivity
{
    public function __construct(
        private WorkflowClientInterface $client,
    ) {}

    #[ActivityMethod('result')]
    public function result(): void
    {
        $this->client->newUntypedRunningWorkflowStub(
            workflowID: Activity::getInfo()->workflowExecution->getID(),
            workflowType: Activity::getInfo()->workflowType->name,
        )->update('my_update');
    }
}
