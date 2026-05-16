<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\MultipleSignals\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class MultipleSignalsWorkflow
{
    /** @var list<string> */
    private array $buffer = [];

    #[WorkflowMethod(name: 'Parity_Basic_MultipleSignals')]
    public function run()
    {
        yield Workflow::await(fn(): bool => \count($this->buffer) >= 3);

        return \implode('|', $this->buffer);
    }

    #[SignalMethod(name: 'push')]
    public function push(string $value): void
    {
        $this->buffer[] = $value;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(MultipleSignalsWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-multiple-signals-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_MultipleSignals',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    \usleep(150_000);
    $stub->signal('push', 'a');
    \usleep(150_000);
    $stub->signal('push', 'b');
    \usleep(150_000);
    $stub->signal('push', 'c');

    $result = $stub->getResult('string');
    if ($result !== 'a|b|c') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
