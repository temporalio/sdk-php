<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Temporal\Api\Workflowservice\V1\DescribeActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\PollActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\RequestCancelActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateActivityExecutionRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Common\Uuid;
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
                FailureConverter::mapFailureToException($failure, $this->converter),
            );
        }

        $result = $outcome->getResult();
        if ($result === null) {
            return null;
        }

        return EncodedValues::fromPayloads($result, $this->converter)->getValue(0, $type);
    }

    public function describe(): ActivityExecutionDescription
    {
        $response = $this->client->DescribeActivityExecution(
            (new DescribeActivityExecutionRequest())
                ->setNamespace($this->clientOptions->namespace)
                ->setActivityId($this->activityId)
                ->setRunId($this->runId ?? ''),
        );

        /** @psalm-suppress PossiblyNullArgument */
        return (new ActivityExecutionMapper($this->converter))->fromInfo($response->getInfo());
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
}
