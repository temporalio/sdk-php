<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\NestedChildWorkflow\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class GrandChildWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_NestedChildWorkflow_GrandChild')]
    public function run(string $name)
    {
        return "g:{$name}";
    }
}

#[WorkflowInterface]
final class ChildWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_NestedChildWorkflow_Child')]
    public function run(string $name)
    {
        $stub = Workflow::newChildWorkflowStub(
            GrandChildWorkflow::class,
            ChildWorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(10)),
        );

        $result = yield $stub->run($name);

        return "c:{$result}";
    }
}

#[WorkflowInterface]
final class ParentWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_NestedChildWorkflow')]
    public function run()
    {
        $stub = Workflow::newChildWorkflowStub(
            ChildWorkflow::class,
            ChildWorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(10)),
        );

        $result = yield $stub->run('hi');

        return "p:{$result}";
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(
        ParentWorkflow::class,
        ChildWorkflow::class,
        GrandChildWorkflow::class,
    );
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-nested-child-workflow-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_NestedChildWorkflow',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'p:c:g:hi') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
