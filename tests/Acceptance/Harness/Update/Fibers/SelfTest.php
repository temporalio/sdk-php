<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\Fibers\Self;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class SelfTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Update_Fibers_Self')]WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult();
        self::assertSame('Hello, world!', $result);
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $done = false;

    #[WorkflowMethod('Harness_Update_Fibers_Self')]
    public function run()
    {
        Workflow::executeActivity(
            'Fibers_result',
            options: ActivityOptions::new()->withStartToCloseTimeout(10),
        );

        Workflow::await(fn(): bool => $this->done);

        return 'Hello, world!';
    }

    #[\Temporal\Workflow\UpdateMethod('my_update')]
    public function myUpdate()
    {
        $this->done = true;
    }
}

#[ActivityInterface(prefix: 'Fibers_')]
class FeatureActivity
{
    public function __construct(
        private WorkflowClientInterface $client,
    ) {}

    #[ActivityMethod('result')]
    public function result(): void
    {
        $workflowStub = $this->client->newUntypedRunningWorkflowStub(
            workflowID: Activity::getInfo()->workflowExecution->getID(),
            workflowType: Activity::getInfo()->workflowType->name,
        );
        $workflowStub->update('my_update');
    }
}
