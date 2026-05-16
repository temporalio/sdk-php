<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\AwaitWithTimeout\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class AwaitWithTimeoutWorkflow
{
    private bool $released = false;

    #[WorkflowMethod(name: 'Parity_Basic_AwaitWithTimeout')]
    public function run()
    {
        $got = yield Workflow::awaitWithTimeout(
            CarbonInterval::seconds(5),
            fn(): bool => $this->released,
        );

        return $got ? 'got' : 'timed-out';
    }

    #[SignalMethod(name: 'release')]
    public function release(): void
    {
        $this->released = true;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(AwaitWithTimeoutWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-await-with-timeout-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_AwaitWithTimeout',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    \usleep(200_000);
    $stub->signal('release');

    $result = $stub->getResult('string');
    if ($result !== 'got') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
