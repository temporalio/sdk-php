<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Activity\Php;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[ActivityInterface(prefix: 'Parity.Basic.Activity.')]
interface EchoActivity
{
    #[ActivityMethod('say')]
    public function say(string $name): string;
}

final class EchoActivityImpl implements EchoActivity
{
    public function say(string $name): string
    {
        return "echoed:{$name}";
    }
}

#[WorkflowInterface]
final class ActivityWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_Activity')]
    public function run(string $name)
    {
        $stub = Workflow::newActivityStub(
            EchoActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(5)),
        );

        return yield $stub->say($name);
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ActivityWorkflow::class);
    $worker->registerActivityImplementations(new EchoActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-activity-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_Activity',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub, 'world');
    $result = $stub->getResult('string');
    if ($result !== 'echoed:world') {
        throw new \RuntimeException("expected 'echoed:world', got: " . \var_export($result, true));
    }

    return $workflowId;
}
