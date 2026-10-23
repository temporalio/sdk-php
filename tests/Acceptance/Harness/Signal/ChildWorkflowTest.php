<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Signal\ChildWorkflow;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ChildWorkflowTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Signal_ChildWorkflow')]WorkflowStubInterface $stub,
    ): void {
        self::assertSame('child-wf-arg', $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_Signal_ChildWorkflow')]
    public function run(): string
    {
        $wf = Workflow::newChildWorkflowStub(
            ChildWorkflow::class,
            Workflow\ChildWorkflowOptions::new()
                // TODO: remove after https://github.com/temporalio/sdk-php/issues/451 is fixed
                ->withTaskQueue(Workflow::getInfo()->taskQueue)
        );
        $handle = Workflow::async(static fn(): string => $wf->run());

        $wf->mySignal('child-wf-arg');
        return $handle->await();
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    private string $value = '';

    #[WorkflowMethod('Harness_Signal_ChildWorkflow_Child')]
    public function run(): string
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
