<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\LocalActivity\Php;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\LocalActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[ActivityInterface(prefix: 'Parity.Basic.LocalActivity.')]
interface EchoActivity
{
    #[ActivityMethod('say')]
    public function say(string $name): string;
}

final class EchoActivityImpl implements EchoActivity
{
    public function say(string $name): string
    {
        return "local-echoed:{$name}";
    }
}

#[WorkflowInterface]
final class LocalActivityWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_LocalActivity')]
    public function run(string $name)
    {
        return yield Workflow::executeActivity(
            'Parity.Basic.LocalActivity.say',
            args: [$name],
            options: LocalActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(5)),
            returnType: \Temporal\DataConverter\Type::TYPE_STRING,
        );
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(LocalActivityWorkflow::class);
    $worker->registerActivityImplementations(new EchoActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-localactivity-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_LocalActivity',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub, 'world');
    $result = $stub->getResult('string');
    if ($result !== 'local-echoed:world') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
