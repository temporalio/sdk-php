<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\WorkflowGetVersion\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class GetVersionWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_WorkflowGetVersion')]
    public function run()
    {
        $version = yield Workflow::getVersion('change-a', 1, 2);

        return "v:{$version}";
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(GetVersionWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-workflow-get-version-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_WorkflowGetVersion',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'v:2') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
