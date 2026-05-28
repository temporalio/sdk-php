<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\ChildWorkflowId;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;

#[CoversFunction('Temporal\Internal\Workflow\Process\Process::logRunningHandlers')]
class ChildWorkflowIdTest extends TestCase
{
    #[Test]
    public function updateHandlersWithOneCall(
        #[Stub('Extra_Workflow_ChildWorkflowId', args: [true])] WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        $deadline = \microtime(true) + 5.0; // 5-second timeout
        do {
            $childId = $stub->query('getChildId')->getValue(0);
            if ($childId !== null) {
                break;
            }
        } while (\microtime(true) < $deadline);

        $childId ?? $this->fail('Child workflow not started.');

        // Get child workflow stub
        $child = $client->newRunningWorkflowStub(TestWorkflow::class, $childId);

        $this->assertSame($stub->getExecution()->getID(), $child->getParentId());
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    /** @var non-empty-string|null */
    private ?string $childId = null;

    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_ChildWorkflowId")]
    public function handle(bool $createChild = false)
    {
        // Start a child workflow and store its ID
        if ($createChild) {
            $child = Workflow::newUntypedChildWorkflowStub("Extra_Workflow_ChildWorkflowId");
            $result = yield $child->start(false);
            $this->childId = $result->getID();
        }

        yield Workflow::await(
            fn(): bool => $this->exit,
        );
    }

    /**
     * @return null|non-empty-string
     */
    #[QueryMethod]
    public function getChildId(): ?string
    {
        return $this->childId;
    }

    /**
     * @return null|non-empty-string
     */
    #[QueryMethod]
    public function getParentId(): ?string
    {
        return Workflow::getInfo()->parentExecution?->getID();
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
