<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\ActivityCancelTryCancel\Php;

use Carbon\CarbonInterval;
use Temporal\Activity;
use Temporal\Activity\ActivityCancellationType;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[ActivityInterface(prefix: 'Parity.Harness.ActivityCancelTryCancel.')]
interface CancellableActivity
{
    #[ActivityMethod('cancellable')]
    public function cancellable(): void;
}

final class CancellableActivityImpl implements CancellableActivity
{
    public function cancellable(): void
    {
        for ($i = 0; $i < 50; $i++) {
            \usleep(100_000);
            try {
                Activity::heartbeat($i);
            } catch (\Temporal\Exception\Client\ActivityCanceledException) {
                return;
            }
        }
    }
}

#[WorkflowInterface]
final class ActivityCancelTryCancelWorkflow
{
    #[WorkflowMethod(name: 'Parity_Harness_ActivityCancelTryCancel')]
    public function run()
    {
        $activity = Workflow::newActivityStub(
            CancellableActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::minute())
                ->withHeartbeatTimeout(CarbonInterval::seconds(5))
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1))
                ->withCancellationType(ActivityCancellationType::TryCancel),
        );

        $scope = Workflow::async(static fn () => $activity->cancellable());

        yield Workflow::timer(CarbonInterval::seconds(1));

        try {
            $scope->cancel();
            yield $scope;
        } catch (CanceledFailure) {
            return 'cancelled';
        }

        return 'unexpected:not-cancelled';
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ActivityCancelTryCancelWorkflow::class);
    $worker->registerActivityImplementations(new CancellableActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-harness-activity-cancel-try-cancel-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Harness_ActivityCancelTryCancel',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'cancelled') {
        throw new \RuntimeException("expected 'cancelled', got: " . \var_export($result, true));
    }

    return $workflowId;
}
