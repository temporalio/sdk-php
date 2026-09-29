<?php

declare(strict_types=1);

use Temporal\Api\Enums\V1\TaskQueueKind;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest;

require __DIR__ . '/vendor/autoload.php';

const NAMESPACE_NAME = 'default';
const ECHO_ACTIVITY = 'BenchActivity.echo';
const RPC_TIMEOUT_SECONDS = 70;

function temporalAddress(): string
{
    return \getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7556';
}

function identity(): string
{
    return \getmypid() . '@' . \gethostname() . '-pure-php';
}

function pollRequest(): PollActivityTaskQueueRequest
{
    return (new PollActivityTaskQueueRequest())
        ->setNamespace(NAMESPACE_NAME)
        ->setIdentity(identity())
        ->setTaskQueue(
            (new TaskQueue())
                ->setName(\getenv('BENCH_TASK_QUEUE') ?: 'bench')
                ->setKind(TaskQueueKind::TASK_QUEUE_KIND_NORMAL),
        );
}

function runActivity(PollActivityTaskQueueResponse $task): RespondActivityTaskCompletedRequest|RespondActivityTaskFailedRequest
{
    $type = $task->getActivityType()?->getName();
    if ($type !== ECHO_ACTIVITY) {
        return (new RespondActivityTaskFailedRequest())
            ->setNamespace(NAMESPACE_NAME)
            ->setIdentity(identity())
            ->setTaskToken($task->getTaskToken())
            ->setFailure(
                (new Failure())
                    ->setMessage("Unknown activity type: {$type}")
                    ->setApplicationFailureInfo((new ApplicationFailureInfo())->setNonRetryable(true)),
            );
    }

    return (new RespondActivityTaskCompletedRequest())
        ->setNamespace(NAMESPACE_NAME)
        ->setIdentity(identity())
        ->setTaskToken($task->getTaskToken())
        ->setResult($task->getInput());
}
