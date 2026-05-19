<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\MultipleActivities\Php;

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

#[ActivityInterface(prefix: 'Parity.Basic.MultipleActivities.')]
interface EchoActivity
{
    #[ActivityMethod('say')]
    public function say(string $word): string;
}

final class EchoActivityImpl implements EchoActivity
{
    public function say(string $word): string
    {
        return "echoed:{$word}";
    }
}

#[WorkflowInterface]
final class MultipleActivitiesWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_MultipleActivities')]
    public function run()
    {
        $stub = Workflow::newActivityStub(
            EchoActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(5)),
        );

        $one = yield $stub->say('one');
        $two = yield $stub->say('two');
        $three = yield $stub->say('three');

        return "{$one}|{$two}|{$three}";
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(MultipleActivitiesWorkflow::class);
    $worker->registerActivityImplementations(new EchoActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-multipleactivities-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_MultipleActivities',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'echoed:one|echoed:two|echoed:three') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
