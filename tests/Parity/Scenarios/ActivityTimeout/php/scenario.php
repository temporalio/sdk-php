<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\ActivityTimeout\Php;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[ActivityInterface(prefix: 'Parity.Basic.ActivityTimeout.')]
interface SlowActivity
{
    #[ActivityMethod('sleepLong')]
    public function sleepLong(): string;
}

final class SlowActivityImpl implements SlowActivity
{
    public function sleepLong(): string
    {
        \sleep(5);
        return 'unreachable';
    }
}

#[WorkflowInterface]
final class ActivityTimeoutWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_ActivityTimeout')]
    public function run()
    {
        $stub = Workflow::newActivityStub(
            SlowActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::milliseconds(500))
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
        );

        try {
            yield $stub->sleepLong();
            return 'unexpected:no-timeout';
        } catch (ActivityFailure) {
            return 'timed-out';
        }
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ActivityTimeoutWorkflow::class);
    $worker->registerActivityImplementations(new SlowActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-activitytimeout-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_ActivityTimeout',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'timed-out') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
