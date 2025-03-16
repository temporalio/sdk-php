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

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
