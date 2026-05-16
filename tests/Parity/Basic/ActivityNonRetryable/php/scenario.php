<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\ActivityNonRetryable\Php;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[ActivityInterface(prefix: 'Parity.Basic.ActivityNonRetryable.')]
interface FatalActivity
{
    #[ActivityMethod('fatal')]
    public function fatal(): string;
}

final class FatalActivityImpl implements FatalActivity
{
    public function fatal(): string
    {
        throw new ApplicationFailure(
            'do-not-retry',
            'FatalAppError',
            true,
        );
    }
}

#[WorkflowInterface]
final class ActivityNonRetryableWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_ActivityNonRetryable')]
    public function run()
    {
        $stub = Workflow::newActivityStub(
            FatalActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::seconds(2))
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withInitialInterval(CarbonInterval::milliseconds(10))
                        ->withBackoffCoefficient(1.0)
                        ->withMaximumAttempts(5),
                ),
        );

        try {
            yield $stub->fatal();
            return 'unexpected:no-failure';
        } catch (ActivityFailure) {
            return 'non-retryable';
        }
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ActivityNonRetryableWorkflow::class);
    $worker->registerActivityImplementations(new FatalActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-activity-non-retryable-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_ActivityNonRetryable',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'non-retryable') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
