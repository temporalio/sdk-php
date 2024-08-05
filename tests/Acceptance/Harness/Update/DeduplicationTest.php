<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\Deduplication;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\Update\LifecycleStage;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DeduplicationTest extends TestCase
{
    #[Test]
    public function check(
        #[Stub('HarnessWorkflow_Update_Deduplication')]WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        $updateId = 'incrementer';
        # Issue async update

        $handle1 = $stub->startUpdate(
            UpdateOptions::new('my_update', LifecycleStage::StageAccepted)
                ->withUpdateId($updateId),
        );
        $handle2 = $stub->startUpdate(
            UpdateOptions::new('my_update', LifecycleStage::StageAccepted)
                ->withUpdateId($updateId),
        );

        $stub->signal('unblock');

        self::assertSame(1, $handle1->getResult(1));
        self::assertSame(1, $handle2->getResult(1));

        # This only needs to start to unblock the workflow
        $stub->startUpdate('my_update');

        # There should be two accepted updates, and only one of them should be completed with the set id
        $totalUpdates = 0;
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            $event->hasWorkflowExecutionUpdateAcceptedEventAttributes() and ++$totalUpdates;

            $f = $event->getWorkflowExecutionUpdateCompletedEventAttributes();
            $f === null or self::assertSame($updateId, $f->getMeta()?->getUpdateId());
        }

        self::assertSame(2, $totalUpdates);
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private int $counter = 0;
    private bool $blocked = true;

    #[WorkflowMethod('HarnessWorkflow_Update_Deduplication')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->counter >= 2);
        return $this->counter;
    }

    #[Workflow\SignalMethod('unblock')]
    public function unblock()
    {
        $this->blocked = false;
    }

    #[Workflow\UpdateMethod('my_update')]
    public function myUpdate()
    {
        ++$this->counter;
        # Verify that dedupe works pre-update-completion
        yield Workflow::await(fn(): bool => !$this->blocked);
        $this->blocked = true;
        return $this->counter;
    }
}
