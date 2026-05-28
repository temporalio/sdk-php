<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\WorkflowMetadata;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Sdk\V1\WorkflowMetadata;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;

class WorkflowMetadataTest extends TestCase
{
    #[Test]
    public function metadataQuery(
        #[Stub('Extra_Workflow_WorkflowMetadata', args: ["from test"])] WorkflowStubInterface $stub,
    ): void {
        $values = $stub->query('__temporal_workflow_metadata');
        /**
         * @var WorkflowMetadata|null $metadata
         */
        $metadata = $values->getValue(0, WorkflowMetadata::class);

        $stub->signal('exit');
        $this->assertSame("Cooking workflow from test", $metadata->getCurrentDetails());
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_WorkflowMetadata")]
    public function handle(string $payload)
    {
        Workflow::setCurrentDetails("Cooking workflow " . $payload);

        yield Workflow::await(fn() => $this->exit);
    }

    /**
     * @return null|non-empty-string
     */
    #[QueryMethod]
    public function getCurrentDetails(): ?string
    {
        return Workflow::getCurrentDetails();
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
