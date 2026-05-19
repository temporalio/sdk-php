<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Harness\QueryUnexpectedArguments\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class QueryUnexpectedArgumentsWorkflow
{
    private bool $done = false;

    #[WorkflowMethod(name: 'Parity_Harness_QueryUnexpectedArguments')]
    public function run()
    {
        yield Workflow::await(fn (): bool => $this->done);
        return 'finished';
    }

    #[QueryMethod('the_query')]
    public function theQuery(int $arg): string
    {
        return "got {$arg}";
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->done = true;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(QueryUnexpectedArgumentsWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-harness-query-unexpected-arguments-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Harness_QueryUnexpectedArguments',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);

    \usleep(200_000);

    $queryResult = $stub->query('the_query', 42)?->getValue(0);
    if ($queryResult !== 'got 42') {
        throw new \RuntimeException("expected query result 'got 42', got: " . \var_export($queryResult, true));
    }

    $stub->signal('finish');
    $result = $stub->getResult('string');
    if ($result !== 'finished') {
        throw new \RuntimeException("expected 'finished', got: " . \var_export($result, true));
    }

    return $workflowId;
}
