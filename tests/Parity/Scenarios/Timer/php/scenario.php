<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Timer\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class TimerWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_Timer')]
    public function run()
    {
        yield Workflow::timer(CarbonInterval::seconds(1));

        return 'done';
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(TimerWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-timer-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_Timer',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(10)),
    );

    $client->start($stub);

    $result = $stub->getResult('string');
    if ($result !== 'done') {
        throw new \RuntimeException("expected 'done', got: " . \var_export($result, true));
    }

    return $workflowId;
}
