<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\SignalWithArg\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class SignalWithArgWorkflow
{
    private ?string $payload = null;

    #[WorkflowMethod(name: 'Parity_Basic_SignalWithArg')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->payload !== null);

        return "hi:{$this->payload}";
    }

    #[SignalMethod(name: 'greet')]
    public function greet(string $value): void
    {
        $this->payload = $value;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(SignalWithArgWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-signal-with-arg-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_SignalWithArg',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    \usleep(200_000);
    $stub->signal('greet', 'world');

    $result = $stub->getResult('string');
    if ($result !== 'hi:world') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
