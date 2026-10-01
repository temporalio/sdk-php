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
use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\DoNotCompleteOnResultException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\TransportException;
use Temporal\Internal\Activity\ActivityContext;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\WorkerFactory;

final class ActivityTasks implements RPCConnectionInterface
{
    public const SIDE_EFFECT = '__php_side_effect';

    private ?Bridge $bridge = null;

    /** @var array<string, array{\FFI\CData, ?Cancel}> */
    private array $running = [];

    /** @var \Closure(list<CommandInterface>, array): list<CommandInterface> */
    private \Closure $dispatch;

    private readonly PayloadMapper $payloads;
    private readonly InfoFactory $info;

    public function __construct(DataConverterInterface $converter)
    {
        $this->payloads = new PayloadMapper($converter);
        $this->info = new InfoFactory($this->payloads);
    }

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

        $result = $this->execute($worker, $taskQueue, $task->getTaskToken(), $task->getStart());

        return (new ActivityTaskCompletion(['task_token' => $task->getTaskToken(), 'result' => $result]))->serializeToString();
    }

    public function call(string $method, $payload): array
    {
        if ($method !== ActivityContext::HEARTBEAT_METHOD) {
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

    private function execute(\FFI\CData $worker, string $taskQueue, string $token, Start $start): ActivityExecutionResult
    {
        if ($start->getActivityType() === self::SIDE_EFFECT) {
            $input = $start->getInput();

            return new ActivityExecutionResult(['completed' => new ActivitySuccess(['result' => \count($input) > 0 ? $input[0] : null])]);
        }

        $this->running[$token] = [$worker, null];

        try {
            $responses = ($this->dispatch)([$this->request($token, $start, $taskQueue)], [WorkerFactory::HEADER_TASK_QUEUE => $taskQueue]);

            return $this->result($responses, $this->running[$token][1]);
        } finally {
            unset($this->running[$token]);
        }
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
                return new ActivityExecutionResult(['completed' => new ActivitySuccess([
                    'result' => $this->payloads->firstPayload($response->getPayloads()),
                ])]);
            }

            if ($response instanceof FailedClientResponse) {
                $error = $response->getFailure();
                if ($error instanceof DoNotCompleteOnResultException) {
                    return new ActivityExecutionResult(['will_complete_async' => new WillCompleteAsync()]);
                }

                if ($cancel !== null && ($error instanceof ActivityCanceledException || $error instanceof CanceledFailure)) {
                    return new ActivityExecutionResult(['cancelled' => new Cancellation([
                        'failure' => $this->payloads->failure(new CanceledFailure($error->getMessage())),
                    ])]);
                }

                return new ActivityExecutionResult(['failed' => new ActivityFailure([
                    'failure' => $this->payloads->failure($error),
                ])]);
            }
        }

        throw new \LogicException('Activity produced no result');
    }

    private function request(string $token, Start $start, string $taskQueue): ServerRequest
    {
        $input = $start->getInput();
        $details = $start->getHeartbeatDetails();
        $payloads = \count($details) === 0 ? $input : new \ArrayIterator([...$input, ...$details]);

        return new ServerRequest(
            name: $start->getIsLocal() ? 'InvokeLocalActivity' : 'InvokeActivity',
            info: new TickInfo(new \DateTimeImmutable()),
            options: [
                'name' => $start->getActivityType(),
                'info' => $this->info->activityInfo($token, $start, $taskQueue),
                'heartbeatDetails' => \count($details),
            ],
            payloads: $this->payloads->values($payloads),
            id: $start->getActivityId(),
            header: $this->payloads->header($start->getHeaderFields()),
        );
    }
}
