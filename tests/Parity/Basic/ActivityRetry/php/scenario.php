<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\ActivityRetry\Php;

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

#[ActivityInterface(prefix: 'Parity.Basic.ActivityRetry.')]
interface FlakyActivity
{
    #[ActivityMethod('fail')]
    public function fail(): string;
}

final class FlakyActivityImpl implements FlakyActivity
{
    public function fail(): string
    {
        throw new \RuntimeException('always-fails');
    }
}

#[WorkflowInterface]
final class ActivityRetryWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_ActivityRetry')]
    public function run()
    {
        $stub = Workflow::newActivityStub(
            FlakyActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::seconds(2))
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withInitialInterval(CarbonInterval::milliseconds(10))
                        ->withBackoffCoefficient(1.0)
                        ->withMaximumAttempts(3),
                ),
        );

        try {
            yield $stub->fail();
            return 'unexpected:no-failure';
        } catch (ActivityFailure) {
            return 'caught';
        }
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ActivityRetryWorkflow::class);
    $worker->registerActivityImplementations(new FlakyActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-activityretry-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_ActivityRetry',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'caught') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
