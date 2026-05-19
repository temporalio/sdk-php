<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\ConcurrentActivities\Php;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Promise;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[ActivityInterface(prefix: 'Parity.Basic.ConcurrentActivities.')]
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
final class ConcurrentActivitiesWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_ConcurrentActivities')]
    public function run()
    {
        $stub = Workflow::newActivityStub(
            EchoActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(5)),
        );

        $a = $stub->say('a');
        $b = $stub->say('b');
        $c = $stub->say('c');

        [$ra, $rb, $rc] = yield Promise::all([$a, $b, $c]);

        return "{$ra}|{$rb}|{$rc}";
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ConcurrentActivitiesWorkflow::class);
    $worker->registerActivityImplementations(new EchoActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-concurrentactivities-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_ConcurrentActivities',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'echoed:a|echoed:b|echoed:c') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
