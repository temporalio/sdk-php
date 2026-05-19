<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\QueryUnexpectedReturnType\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\DataConverterException;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class QueryUnexpectedReturnTypeWorkflow
{
    private bool $done = false;

    #[WorkflowMethod(name: 'Parity_Harness_QueryUnexpectedReturnType')]
    public function run()
    {
        yield Workflow::await(fn (): bool => $this->done);
        return 'finished';
    }

    #[QueryMethod('the_query')]
    public function theQuery(): string
    {
        return 'hi bob';
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->done = true;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(QueryUnexpectedReturnTypeWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-harness-query-unexpected-return-type-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Harness_QueryUnexpectedReturnType',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);

    \usleep(200_000);

    $caught = false;
    try {
        $stub->query('the_query')?->getValue(0, 'int');
    } catch (DataConverterException) {
        $caught = true;
    }
    if (!$caught) {
        throw new \RuntimeException('expected DataConverterException for bad return type');
    }

    $stub->signal('finish');
    $result = $stub->getResult('string');
    if ($result !== 'finished') {
        throw new \RuntimeException("expected 'finished', got: " . \var_export($result, true));
    }

    return $workflowId;
}
