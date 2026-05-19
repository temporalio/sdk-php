<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\ContinueAsNew\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class CounterWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_ContinueAsNew')]
    public function run(int $i)
    {
        if ($i < 2) {
            yield Workflow::continueAsNew('Parity_Basic_ContinueAsNew', [$i + 1]);
        }

        return "done:{$i}";
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(CounterWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-continueasnew-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_ContinueAsNew',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
    );

    $run = $client->start($stub, 0);
    $firstRunId = $run->getExecution()->getRunID();
    if ($firstRunId === null || $firstRunId === '') {
        throw new \RuntimeException('failed to capture first runId from start()');
    }
    echo "RUN_ID={$firstRunId}\n";

    $stub->getResult('string');

    return $workflowId;
}
