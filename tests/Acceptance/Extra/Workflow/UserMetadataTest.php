<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\UserMetadata;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class UserMetadataTest extends TestCase
{
    #[Test]
    public function initMetadata(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_UserMetadata',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withStaticSummary('test summary')
                ->withStaticDetails('test details'),
        );

        try {
            /** @see TestWorkflow::handle() */
            $client->start($stub);
            $stub->update('ping');

            $description = $stub->describe();
            self::assertSame('test summary', $description->config->userMetadata->summary);
            self::assertSame('test details', $description->config->userMetadata->details);

            // Complete workflow
            /** @see TestWorkflow::exit */
            $stub->signal('exit');
            $stub->getResult();

            $description = $stub->describe();
            self::assertSame('test summary', $description->config->userMetadata->summary);
            self::assertSame('test details', $description->config->userMetadata->details);
        } finally {
            $stub->terminate('Finalize');
        }
    }

    #[Test]
    public function childWorkflowMetadata(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_UserMetadata',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withStaticSummary('test summary')
                ->withStaticDetails('test details'),
        );

        try {
            /** @see TestWorkflow::handle() */
            $client->start($stub);
            $childId = (string) $stub->update('start_child', 'child summary', 'child details')->getValue(0);

            $child = $client->newUntypedRunningWorkflowStub($childId);
            $description = $child->describe();
            self::assertSame('child summary', $description->config->userMetadata->summary);
            self::assertSame('child details', $description->config->userMetadata->details);
        } finally {
            $stub->terminate('Finalize');
        }
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $result = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_UserMetadata")]
    public function handle()
    {
        yield Workflow::await(fn() => $this->exit);
        return $this->result;
    }

    #[Workflow\UpdateMethod]
    public function ping(): string
    {
        return 'pong';
    }

    #[Workflow\UpdateMethod('start_child')]
    public function startChild(string $summary, string $details)
    {
        $stub = Workflow::newUntypedChildWorkflowStub(
            'Extra_Workflow_UserMetadata',
            Workflow\ChildWorkflowOptions::new()->withStaticSummary($summary)->withStaticDetails($details),
        );
        $execution = yield $stub->start();

        return $execution->getID();
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
