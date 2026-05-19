<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Harness\QueryUnexpectedTypeName\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\WorkflowQueryException;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class QueryUnexpectedTypeNameWorkflow
{
    private bool $done = false;

    #[WorkflowMethod(name: 'Parity_Harness_QueryUnexpectedTypeName')]
    public function run()
    {
        yield Workflow::await(fn (): bool => $this->done);
        return 'finished';
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->done = true;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(QueryUnexpectedTypeNameWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-harness-query-unexpected-type-name-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Harness_QueryUnexpectedTypeName',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);

    \usleep(200_000);

    $caught = false;
    try {
        $stub->query('nonexistent');
    } catch (WorkflowQueryException) {
        $caught = true;
    }
    if (!$caught) {
        throw new \RuntimeException('expected WorkflowQueryException for unknown query name');
    }

    $stub->signal('finish');
    $result = $stub->getResult('string');
    if ($result !== 'finished') {
        throw new \RuntimeException("expected 'finished', got: " . \var_export($result, true));
    }

    return $workflowId;
}
