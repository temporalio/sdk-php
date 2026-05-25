<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Signal\Fibers\ChildWorkflow;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ChildWorkflowTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Signal_Fibers_ChildWorkflow')]WorkflowStubInterface $stub,
    ): void {
        self::assertSame('child-wf-arg', $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_Signal_Fibers_ChildWorkflow')]
    public function run()
    {
        $wf = Workflow::newChildWorkflowStub(
            ChildWorkflow::class,
            \Temporal\Workflow\ChildWorkflowOptions::new()
                // TODO: remove after https://github.com/temporalio/sdk-php/issues/451 is fixed
                ->withTaskQueue(Workflow::getInfo()->taskQueue)
        );
        $handle = $wf->run();

        $wf->mySignal('child-wf-arg');
        return $handle;
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    private string $value = '';

    #[WorkflowMethod('Harness_Signal_Fibers_ChildWorkflow_Child')]
    public function run()
    {
        Workflow::await(fn(): bool => $this->value !== '');
        return $this->value;
    }

    #[SignalMethod('my_signal')]
    public function mySignal(string $arg)
    {
        $this->value = $arg;
    }
}
