<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\ActivityFailure\Php;

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

#[ActivityInterface(prefix: 'Parity.Basic.ActivityFailure.')]
interface BoomActivity
{
    #[ActivityMethod('boom')]
    public function boom(): string;
}

final class BoomActivityImpl implements BoomActivity
{
    public function boom(): string
    {
        throw new \RuntimeException('boom');
    }
}

#[WorkflowInterface]
final class ActivityFailureWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_ActivityFailure')]
    public function run()
    {
        $stub = Workflow::newActivityStub(
            BoomActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::seconds(2))
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withInitialInterval(CarbonInterval::milliseconds(10))
                        ->withBackoffCoefficient(1.0)
                        ->withMaximumAttempts(1),
                ),
        );

        try {
            yield $stub->boom();
            return 'unexpected:no-failure';
        } catch (ActivityFailure) {
            return 'caught';
        }
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ActivityFailureWorkflow::class);
    $worker->registerActivityImplementations(new BoomActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-activity-failure-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_ActivityFailure',
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
