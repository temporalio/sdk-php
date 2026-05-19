<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\MultipleTimers\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class MultipleTimersWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_MultipleTimers')]
    public function run()
    {
        yield Workflow::timer(CarbonInterval::milliseconds(50));
        yield Workflow::timer(CarbonInterval::milliseconds(50));
        yield Workflow::timer(CarbonInterval::milliseconds(50));

        return 'tick-tick-tick';
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(MultipleTimersWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-multiple-timers-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_MultipleTimers',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'tick-tick-tick') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
