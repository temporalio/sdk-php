<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Activity_result\ActivityExecutionResult;
use Coresdk\Activity_result\Cancellation;
use Coresdk\Activity_result\Failure as ActivityFailure;
use Coresdk\Activity_result\Success as ActivitySuccess;
use Coresdk\Activity_result\WillCompleteAsync;
use Coresdk\Activity_task\ActivityCancelReason;
use Coresdk\Activity_task\ActivityTask;
use Coresdk\Activity_task\Cancel;
use Coresdk\Activity_task\Start;
use Coresdk\ActivityHeartbeat;
use Coresdk\ActivityTaskCompletion;
use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
use Temporal\Activity\ActivityInfo;
use Temporal\Activity\ActivityType;
use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\DoNotCompleteOnResultException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\TransportException;
use Temporal\Interceptor\Header;
use Temporal\Internal\Support\DateTime;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowType;

final class ActivityTasks implements RPCConnectionInterface
{
    public const SIDE_EFFECT = '__php_side_effect';
    private const HEARTBEAT = 'temporal.RecordActivityHeartbeat';

    private ?Bridge $bridge = null;

    /** @var array<string, array{\FFI\CData, ?Cancel}> */
    private array $running = [];

    /** @var \Closure(list<CommandInterface>, array): list<CommandInterface> */
    private \Closure $dispatch;

    public function __construct(
        private readonly DataConverterInterface $converter,
    ) {}

    public function bind(Bridge $bridge, \Closure $dispatch): void
    {
        $this->bridge = $bridge;
        $this->dispatch = $dispatch;
    }

    public function handle(\FFI\CData $worker, string $taskQueue, string $bytes): ?string
    {
        $task = new ActivityTask();
        $task->mergeFromString($bytes);
        if ($task->getVariant() !== 'start') {
            $this->cancel($task);
            return null;
        }

        $start = $task->getStart();
        $token = $task->getTaskToken();
        if ($start->getActivityType() === self::SIDE_EFFECT) {
            return (new ActivityTaskCompletion([
                'task_token' => $token,
                'result' => new ActivityExecutionResult(['completed' => new ActivitySuccess(['result' => $start->getInput()[0] ?? null])]),
            ]))->serializeToString();
        }

        $this->running[$token] = [$worker, null];

        try {
            $responses = ($this->dispatch)([$this->request($token, $start, $taskQueue)], ['taskQueue' => $taskQueue]);
            $result = $this->result($responses, $this->running[$token][1]);
        } finally {
            unset($this->running[$token]);
        }

        return (new ActivityTaskCompletion(['task_token' => $task->getTaskToken(), 'result' => $result]))->serializeToString();
    }

    public function call(string $method, $payload): array
    {
        if ($method !== self::HEARTBEAT) {
            throw new TransportException(\sprintf('RPC method "%s" is not supported by the sdk-core transport', $method));
        }

        $token = \base64_decode($payload['taskToken']);
        if (!isset($this->running[$token])) {
            return [];
        }

        $details = new Payloads();
        $details->mergeFromString(\base64_decode($payload['details']));
        $this->bridge->recordActivityHeartbeat(
            $this->running[$token][0],
            (new ActivityHeartbeat(['task_token' => $token, 'details' => $details->getPayloads()]))->serializeToString(),
        );

        foreach ($this->bridge->peekEvents() as [, $kind, $status, $data]) {
            if ($kind === Bridge::KIND_ACTIVITY_TASK && $status === Bridge::STATUS_OK) {
                $task = new ActivityTask();
                $task->mergeFromString($data);
                $this->cancel($task);
            }
        }

        $cancel = $this->running[$token][1] ?? null;
        if ($cancel === null) {
            return [];
        }

        return match ($cancel->getReason()) {
            ActivityCancelReason::PAUSED => ['paused' => true],
            ActivityCancelReason::RESET => ['reset' => true],
            default => ['canceled' => true],
        };
    }

    private function cancel(ActivityTask $task): void
    {
        if ($task->getVariant() === 'cancel' && isset($this->running[$task->getTaskToken()])) {
            $this->running[$task->getTaskToken()][1] = $task->getCancel();
        }
    }

    /**
     * @param list<CommandInterface> $responses
     */
    private function result(array $responses, ?Cancel $cancel): ActivityExecutionResult
    {
        foreach ($responses as $response) {
            if ($response instanceof SuccessClientResponse) {
                $payloads = $response->getPayloads();
                $payloads->setDataConverter($this->converter);
                $list = $payloads->toPayloads()->getPayloads();

                return new ActivityExecutionResult(['completed' => new ActivitySuccess(['result' => \count($list) > 0 ? $list[0] : null])]);
            }

            if ($response instanceof FailedClientResponse) {
                $error = $response->getFailure();
                if ($error instanceof DoNotCompleteOnResultException) {
                    return new ActivityExecutionResult(['will_complete_async' => new WillCompleteAsync()]);
                }

                if ($cancel !== null && ($error instanceof ActivityCanceledException || $error instanceof CanceledFailure)) {
                    return new ActivityExecutionResult(['cancelled' => new Cancellation([
                        'failure' => Failures::fromThrowable(new CanceledFailure($error->getMessage()), $this->converter),
                    ])]);
                }

                return new ActivityExecutionResult(['failed' => new ActivityFailure([
                    'failure' => Failures::fromThrowable($error, $this->converter),
                ])]);
            }
        }

        throw new \LogicException('Activity produced no result');
    }

    private function request(string $token, Start $start, string $taskQueue): ServerRequest
    {
        $input = $start->getInput();
        $details = $start->getHeartbeatDetails();
        $payloads = \count($details) === 0 ? $input : (new Payloads(['payloads' => [...$input, ...$details]]))->getPayloads();

        return new ServerRequest(
            name: $start->getIsLocal() ? 'InvokeLocalActivity' : 'InvokeActivity',
            info: new TickInfo(new \DateTimeImmutable()),
            options: [
                'name' => $start->getActivityType(),
                'info' => $this->info($token, $start, $taskQueue),
                'heartbeatDetails' => \count($details),
            ],
            payloads: EncodedValues::fromPayloadCollection($payloads, $this->converter),
            id: $start->getActivityId(),
            header: Header::fromPayloadCollection($start->getHeaderFields(), $this->converter),
        );
    }

    /**
     * @psalm-suppress InaccessibleProperty, ArgumentTypeCoercion
     */
    private function info(string $token, Start $start, string $taskQueue): ActivityInfo
    {
        $startedTime = $this->time($start->getStartedTime());
        $timeout = $this->nanos($start->getStartToCloseTimeout()) ?: $this->nanos($start->getScheduleToCloseTimeout());

        $info = (new \ReflectionClass(ActivityInfo::class))->newInstanceWithoutConstructor();
        $info->taskToken = $token;
        $info->workflowType = new WorkflowType();
        $info->workflowType->name = $start->getWorkflowType();
        $info->workflowNamespace = $start->getWorkflowNamespace();
        $info->workflowExecution = new WorkflowExecution(
            $start->getWorkflowExecution()?->getWorkflowId() ?? '',
            $start->getWorkflowExecution()?->getRunId() ?? '',
        );
        $info->id = $start->getActivityId();
        $info->type = new ActivityType();
        $info->type->name = $start->getActivityType();
        $info->taskQueue = $taskQueue;
        $info->heartbeatTimeout = WorkflowActivations::interval($start->getHeartbeatTimeout());
        $info->scheduledTime = DateTime::parse($this->time($start->getScheduledTime())->format(\DATE_RFC3339_EXTENDED));
        $info->startedTime = DateTime::parse($startedTime->format(\DATE_RFC3339_EXTENDED));
        $info->deadline = DateTime::parse(
            $startedTime->modify(\sprintf('+%d microseconds', \intdiv($timeout, 1000)))->format(\DATE_RFC3339_EXTENDED),
        );
        $info->attempt = $start->getAttempt();
        $info->priority = WorkflowActivations::priorityOptions($start->getPriority());
        $info->retryOptions = WorkflowActivations::retryOptions($start->getRetryPolicy());

        return $info;
    }

    private function time(?Timestamp $timestamp): \DateTimeImmutable
    {
        return $timestamp === null
            ? new \DateTimeImmutable()
            : \DateTimeImmutable::createFromInterface($timestamp->toDateTime());
    }

    private function nanos(?Duration $duration): int
    {
        return $duration === null ? 0 : $duration->getSeconds() * 1_000_000_000 + $duration->getNanos();
    }
}
