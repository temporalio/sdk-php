<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Signal\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class WaitForSignalWorkflow
{
    private bool $signaled = false;

    #[WorkflowMethod(name: 'Parity_Basic_Signal')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->signaled);

        return 'signaled';
    }

    #[SignalMethod(name: 'release')]
    public function release(): void
    {
        $this->signaled = true;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(WaitForSignalWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-signal-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_Signal',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    \usleep(200_000);
    $stub->signal('release');

    $result = $stub->getResult('string');
    if ($result !== 'signaled') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
