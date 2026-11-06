<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Temporal\Api\Activity\V1\ActivityExecutionInfo;
use Temporal\Api\Workflowservice\V1\DescribeActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\PollActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\PollActivityExecutionResponse;
use Temporal\Api\Workflowservice\V1\RequestCancelActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateActivityExecutionRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Common\Uuid;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Client\ActivityFailedException;
use Temporal\Exception\Client\ActivityNotFoundException;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Interceptor\ActivityClient\CancelInput;
use Temporal\Interceptor\ActivityClient\DescribeInput;
use Temporal\Interceptor\ActivityClient\GetResultInput;
use Temporal\Interceptor\ActivityClient\TerminateInput;
use Temporal\Interceptor\ActivityClientCallsInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Mapper\ActivityExecutionMapper;
use Temporal\Internal\Support\DateInterval;

final class ActivityHandle
{
    private const POLL_TIMEOUT_MS = 60_000;
    private const SERVER_LONG_POLL_BUFFER_SECONDS = 1.0;
    private const EMPTY_POLL_PAUSE_MICROSECONDS = 100_000;

    /**
     * @param Pipeline<ActivityClientCallsInterceptor, mixed> $interceptors
     *
     * @internal
     */
    public function __construct(
        private readonly ServiceClientInterface $client,
        private readonly ClientOptions $clientOptions,
        private readonly DataConverterInterface $converter,
        private readonly Pipeline $interceptors,
        private readonly string $namespace,
        private readonly string $activityId,
        private readonly ?string $runId,
        private ?ActivitySerializationContext $serializationContext = null,
    ) {}

    public function getID(): string
    {
        return $this->activityId;
    }

    public function getRunID(): ?string
    {
        return $this->runId;
    }

    /**
     * @param int<1, max>|null $timeout
     *
     * @throws ActivityFailedException
     * @throws ActivityNotFoundException
     * @throws TimeoutException
     */
    public function getResult(mixed $type = null, ?int $timeout = null): mixed
    {
        $result = $this->interceptors->with(
            fn(GetResultInput $input): ?ValuesInterface => $this->fetchResult($input->timeout),
            'getResult',
        )(new GetResultInput($this->activityId, $this->runId, $timeout, $type));
        \assert($result === null || $result instanceof ValuesInterface);

        if ($result === null || $result->count() === 0) {
            return null;
        }

        return $result->getValue(0, $type);
    }

    /**
     * @throws ActivityNotFoundException
     */
    public function describe(): ActivityExecutionDescription
    {
        $description = $this->interceptors->with(
            fn(DescribeInput $input): ActivityExecutionDescription => (new ActivityExecutionMapper(
                $this->converter,
                $this->namespace,
            ))->fromInfo($this->describeExecution($input->runId ?? '', true)),
            'describe',
        )(new DescribeInput($this->activityId, $this->runId));
        \assert($description instanceof ActivityExecutionDescription);

        return $description;
    }

    /**
     * @throws ActivityNotFoundException
     */
    public function cancel(string $reason = ''): void
    {
        $this->interceptors->with(
            function (CancelInput $input): void {
                $this->call(fn() => $this->client->RequestCancelActivityExecution(
                    (new RequestCancelActivityExecutionRequest())
                        ->setNamespace($this->namespace)
                        ->setActivityId($input->activityId)
                        ->setRunId($input->runId ?? '')
                        ->setIdentity($this->clientOptions->identity)
                        ->setRequestId(Uuid::v4())
                        ->setReason($input->reason),
                ));
            },
            'cancel',
        )(new CancelInput($this->activityId, $this->runId, $reason));
    }

    /**
     * @throws ActivityNotFoundException
     */
    public function terminate(string $reason = ''): void
    {
        $this->interceptors->with(
            function (TerminateInput $input): void {
                $this->call(fn() => $this->client->TerminateActivityExecution(
                    (new TerminateActivityExecutionRequest())
                        ->setNamespace($this->namespace)
                        ->setActivityId($input->activityId)
                        ->setRunId($input->runId ?? '')
                        ->setIdentity($this->clientOptions->identity)
                        ->setRequestId(Uuid::v4())
                        ->setReason($input->reason),
                ));
            },
            'terminate',
        )(new TerminateInput($this->activityId, $this->runId, $reason));
    }

    private function fetchResult(?int $timeout): ?ValuesInterface
    {
        $request = (new PollActivityExecutionRequest())
            ->setNamespace($this->namespace)
            ->setActivityId($this->activityId)
            ->setRunId($this->runId ?? '');
        $deadline = $timeout === null ? null : \microtime(true) + (float) $timeout;

        do {
            $response = $this->poll($request, $deadline);
            $outcome = $response?->getOutcome();
            if ($outcome === null && $this->isInsideServerBuffer($deadline)) {
                \usleep(self::EMPTY_POLL_PAUSE_MICROSECONDS);
            }
        } while ($outcome === null);
        \assert($response instanceof PollActivityExecutionResponse);

        $failure = $outcome->getFailure();
        if ($failure !== null) {
            throw new ActivityFailedException(
                $this->activityId,
                $response->getRunId(),
                $outcome->getRetryState(),
                FailureConverter::mapFailureToException(
                    $failure,
                    $this->converter,
                    $this->getSerializationContext($response->getRunId()),
                ),
            );
        }

        $result = $outcome->getResult();
        if ($result === null) {
            return null;
        }

        return EncodedValues::fromPayloads($result, $this->converter)
            ->withSerializationContext($this->getSerializationContext($response->getRunId()));
    }

    private function isInsideServerBuffer(?float $deadline): bool
    {
        return $deadline !== null && $deadline - \microtime(true) < self::SERVER_LONG_POLL_BUFFER_SECONDS;
    }

    private function poll(PollActivityExecutionRequest $request, ?float $deadline): ?PollActivityExecutionResponse
    {
        $timeoutMs = self::POLL_TIMEOUT_MS;
        if ($deadline !== null) {
            $timeoutMs = \min($timeoutMs, (int) \ceil(($deadline - \microtime(true)) * 1000.0));
            if ($timeoutMs <= 0) {
                throw new TimeoutException('Unable to wait for activity completion, deadline reached');
            }
        }

        try {
            return $this->call(fn() => $this->client->PollActivityExecution(
                $request,
                $this->client->getContext()->withTimeout($timeoutMs, DateInterval::FORMAT_MILLISECONDS),
            ));
        } catch (TimeoutException $e) {
            if ($deadline !== null && \microtime(true) >= $deadline) {
                throw $e;
            }

            return null;
        }
    }

    private function getSerializationContext(string $runId): ActivitySerializationContext
    {
        if ($this->serializationContext !== null) {
            return $this->serializationContext;
        }

        $info = $this->describeExecution($runId, false);
        $context = new ActivitySerializationContext(
            namespace: $this->namespace,
            activityType: $info->getActivityType()?->getName() ?? '',
            taskQueue: $info->getTaskQueue(),
        );

        if ($this->runId !== null) {
            $this->serializationContext = $context;
        }

        return $context;
    }

    /**
     * @psalm-suppress InvalidNullableReturnType
     */
    private function describeExecution(string $runId, bool $includeDetails): ActivityExecutionInfo
    {
        $response = $this->call(fn() => $this->client->DescribeActivityExecution(
            (new DescribeActivityExecutionRequest())
                ->setNamespace($this->namespace)
                ->setActivityId($this->activityId)
                ->setRunId($runId)
                ->setIncludeHeartbeatDetails($includeDetails)
                ->setIncludeLastFailure($includeDetails),
        ));

        /** @psalm-suppress NullableReturnStatement */
        return $response->getInfo();
    }

    /**
     * @template T
     * @param \Closure(): T $call
     * @return T
     */
    private function call(\Closure $call): mixed
    {
        try {
            return $call();
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw new ActivityNotFoundException($this->activityId, $this->runId, $e);
            }

            throw $e;
        }
    }
}
