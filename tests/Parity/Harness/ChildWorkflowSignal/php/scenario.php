<?php

declare(strict_types=1);

namespace TemporalTestsParityHarnessChildWorkflowSignalPhp;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class ChildWorkflowSignalWorkflow
{
    #[WorkflowMethod(name: 'Parity_Harness_ChildWorkflowSignal')]
    public function run()
    {
        return 'todo';
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ChildWorkflowSignalWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'harness-child-workflow-signal-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Harness_ChildWorkflowSignal',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $stub->getResult('string');

    return $workflowId;
}
