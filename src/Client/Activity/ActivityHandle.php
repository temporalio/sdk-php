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
use Temporal\Api\Workflowservice\V1\RequestCancelActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateActivityExecutionRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Common\Uuid;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityFailedException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Internal\Mapper\ActivityExecutionMapper;

final class ActivityHandle
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ServiceClientInterface $client,
        private readonly ClientOptions $clientOptions,
        private readonly DataConverterInterface $converter,
        private readonly string $activityId,
        private readonly ?string $runId,
        private ?ActivitySerializationContext $serializationContext = null,
    ) {}

    public function getId(): string
    {
        return $this->activityId;
    }

    public function getRunId(): ?string
    {
        return $this->runId;
    }

    /**
     * @param int<1, max>|null $timeout
     *
     * @throws ActivityFailedException
     * @throws TimeoutException
     */
    public function getResult(mixed $type = null, ?int $timeout = null): mixed
    {
        $request = (new PollActivityExecutionRequest())
            ->setNamespace($this->clientOptions->namespace)
            ->setActivityId($this->activityId)
            ->setRunId($this->runId ?? '');

        do {
            $start = \time();
            $response = $this->client->PollActivityExecution(
                $request,
                $timeout === null ? null : $this->client->getContext()->withTimeout($timeout),
            );

            if ($timeout !== null) {
                $timeout = \max(0, $timeout - (\time() - $start));
                if ($timeout === 0 && $response->getOutcome() === null) {
                    throw new TimeoutException('Unable to wait for activity completion, deadline reached');
                }
            }

            $outcome = $response->getOutcome();
        } while ($outcome === null);

        $failure = $outcome->getFailure();
        if ($failure !== null) {
            throw new ActivityFailedException(
                $this->activityId,
                $this->runId ?? $response->getRunId(),
                FailureConverter::mapFailureToException($failure, $this->converter, $this->getSerializationContext()),
            );
        }

        $result = $outcome->getResult();
        if ($result === null) {
            return null;
        }

        return EncodedValues::fromPayloads($result, $this->converter)
            ->withSerializationContext($this->getSerializationContext())
            ->getValue(0, $type);
    }

    public function describe(): ActivityExecutionDescription
    {
        return (new ActivityExecutionMapper($this->converter, $this->clientOptions->namespace))
            ->fromInfo($this->describeExecution());
    }

    public function cancel(string $reason = ''): void
    {
        $this->client->RequestCancelActivityExecution(
            (new RequestCancelActivityExecutionRequest())
                ->setNamespace($this->clientOptions->namespace)
                ->setActivityId($this->activityId)
                ->setRunId($this->runId ?? '')
                ->setIdentity($this->clientOptions->identity)
                ->setRequestId(Uuid::v4())
                ->setReason($reason),
        );
    }

    public function terminate(string $reason = ''): void
    {
        $this->client->TerminateActivityExecution(
            (new TerminateActivityExecutionRequest())
                ->setNamespace($this->clientOptions->namespace)
                ->setActivityId($this->activityId)
                ->setRunId($this->runId ?? '')
                ->setIdentity($this->clientOptions->identity)
                ->setRequestId(Uuid::v4())
                ->setReason($reason),
        );
    }

    private function getSerializationContext(): ActivitySerializationContext
    {
        if ($this->serializationContext === null) {
            $info = $this->describeExecution();
            $this->serializationContext = new ActivitySerializationContext(
                namespace: $this->clientOptions->namespace,
                activityType: $info->getActivityType()?->getName() ?? '',
                taskQueue: $info->getTaskQueue(),
            );
        }

        return $this->serializationContext;
    }

    /**
     * @psalm-suppress InvalidNullableReturnType
     */
    private function describeExecution(): ActivityExecutionInfo
    {
        $response = $this->client->DescribeActivityExecution(
            (new DescribeActivityExecutionRequest())
                ->setNamespace($this->clientOptions->namespace)
                ->setActivityId($this->activityId)
                ->setRunId($this->runId ?? ''),
        );

        /** @psalm-suppress NullableReturnStatement */
        return $response->getInfo();
    }
}
