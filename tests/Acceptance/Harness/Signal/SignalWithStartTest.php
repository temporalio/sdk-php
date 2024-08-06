<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Signal\SignalWithStart;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class SignalWithStartTest extends TestCase
{
    #[Test]
    public static function checkSignalProcessedBeforeHandler(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        self::markTestSkipped('See https://github.com/temporalio/sdk-php/issues/457');

        $stub = $client->newWorkflowStub(
            FeatureWorkflow::class,
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );
        $run = $client->startWithSignal($stub, 'add', [42], [1]);

        self::assertSame(43, $run->getResult(), 'Signal must be processed before WF handler. Result: ' . $run->getResult());
    }

    #[Test]
    public static function checkSignalToExistingWorkflow(
        #[Stub('Harness_Signal_SignalWithStart', args: [-2])] WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub2 = $client->newWorkflowStub(
            FeatureWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                // Reuse same ID
                ->withWorkflowId($stub->getExecution()->getID()),
        );
        $run = $client->startWithSignal($stub2, 'add', [42]);

        self::assertSame(40, $run->getResult(), 'Existing WF must be reused. Result: ' . $run->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private int $value = 0;

    #[WorkflowMethod('Harness_Signal_SignalWithStart')]
    public function run(int $arg = 0)
    {
        $this->value += $arg;

        yield Workflow::await(fn() => $this->value > 0);

        return $this->value;
    }

    #[SignalMethod('add')]
    public function add(int $arg): void
    {
        $this->value += $arg;
    }
}
