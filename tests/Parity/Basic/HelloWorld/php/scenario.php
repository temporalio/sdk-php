<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\HelloWorld\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class HelloWorldWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_HelloWorld')]
    public function run(string $name)
    {
        return "hello, {$name}!";
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(HelloWorldWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-helloworld-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_HelloWorld',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(10)),
    );

    $client->start($stub, 'world');

    $result = $stub->getResult('string');
    if ($result !== 'hello, world!') {
        throw new \RuntimeException("expected 'hello, world!', got: " . \var_export($result, true));
    }

    return $workflowId;
}
