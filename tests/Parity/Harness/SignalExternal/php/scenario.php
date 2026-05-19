<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Harness\SignalExternal\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class SignalExternalWorkflow
{
    private ?string $result = null;

    #[WorkflowMethod(name: 'Parity_Harness_SignalExternal')]
    public function run()
    {
        yield Workflow::await(fn (): bool => $this->result !== null);
        return $this->result;
    }

    #[SignalMethod('external_signal')]
    public function externalSignal(string $value): void
    {
        $this->result = $value;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(SignalExternalWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-harness-signal-external-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Harness_SignalExternal',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $stub->signal('external_signal', 'Signaled!');

    $result = $stub->getResult('string');
    if ($result !== 'Signaled!') {
        throw new \RuntimeException("expected 'Signaled!', got: " . \var_export($result, true));
    }

    return $workflowId;
}
