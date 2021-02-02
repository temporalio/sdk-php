<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Api\Workflowservice\V1 as Proto;
use Temporal\Client\ActivityCompletionClientInterface;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\GRPC\StatusCode;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\Client\ActivityCompletionFailureException;
use Temporal\Exception\Client\ActivityNotExistsException;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Failure\FailureConverter;

final class ActivityCompletionClient implements ActivityCompletionClientInterface
{
    private ServiceClientInterface $client;
    private ClientOptions $clientOptions;
    private DataConverterInterface $converter;

    /**
     * @param ServiceClientInterface $client
     * @param ClientOptions $clientOptions
     * @param DataConverterInterface $converter
     */
    public function __construct(
        ServiceClientInterface $client,
        ClientOptions $clientOptions,
        DataConverterInterface $converter
    ) {
        $this->client = $client;
        $this->clientOptions = $clientOptions;
        $this->converter = $converter;
    }

    /**
     * {@inheritDoc}
     */
    public function complete(string $workflowId, ?string $runId, string $activityId, $result = null): void
    {
        $r = new Proto\RespondActivityTaskCompletedByIdRequest();
        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setWorkflowId($workflowId)
            ->setRunId($runId ?? '')
            ->setActivityId($activityId);

        $input = EncodedValues::fromValues(array_slice(func_get_args(), 3), $this->converter);
        if (!$input->isEmpty()) {
            $r->setResult($input->toPayloads());
        }

        try {
            $this->client->RespondActivityTaskCompletedById($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw ActivityNotExistsException::fromPreviousWithActivityId($activityId, $e);
            }

            throw ActivityCompletionFailureException::fromPreviousWithActivityId($activityId, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function completeByToken(string $taskToken, $result = null): void
    {
        $r = new Proto\RespondActivityTaskCompletedRequest();

        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskToken($taskToken);

        $input = EncodedValues::fromValues(array_slice(func_get_args(), 1), $this->converter);
        if (!$input->isEmpty()) {
            $r->setResult($input->toPayloads());
        }

        try {
            $this->client->RespondActivityTaskCompleted($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw ActivityNotExistsException::fromPrevious($e);
            }

            throw ActivityCompletionFailureException::fromPrevious($e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function completeExceptionally(
        string $workflowId,
        ?string $runId,
        string $activityId,
        \Throwable $error
    ): void {
        $r = new Proto\RespondActivityTaskFailedByIdRequest();
        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setWorkflowId($workflowId)
            ->setRunId($runId ?? '')
            ->setActivityId($activityId)
            ->setFailure(FailureConverter::mapExceptionToFailure($error, $this->converter));

        try {
            $this->client->RespondActivityTaskFailedById($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw ActivityNotExistsException::fromPreviousWithActivityId($activityId, $e);
            }

            throw ActivityCompletionFailureException::fromPreviousWithActivityId($activityId, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function completeExceptionallyByToken(string $taskToken, \Throwable $error): void
    {
        $r = new Proto\RespondActivityTaskFailedRequest();
        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskToken($taskToken)
            ->setFailure(FailureConverter::mapExceptionToFailure($error, $this->converter));

        try {
            $this->client->RespondActivityTaskFailed($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw ActivityNotExistsException::fromPrevious($e);
            }

            throw ActivityCompletionFailureException::fromPrevious($e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function reportCancellation(string $workflowId, ?string $runId, string $activityId, $details = null): void
    {
        $r = new Proto\RespondActivityTaskCanceledByIdRequest();
        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setWorkflowId($workflowId)
            ->setRunId($runId ?? '')
            ->setActivityId($activityId);

        if (func_num_args() == 4) {
            $r->setDetails(EncodedValues::fromValues([$details], $this->converter)->toPayloads());
        }

        try {
            $this->client->RespondActivityTaskCanceledById($r);
        } catch (ServiceClientException $e) {
            // There is nothing that can be done at this point so let's just ignore.
        }
    }

    /**
     * {@inheritDoc}
     */
    public function reportCancellationByToken(string $taskToken, $details = null): void
    {
        $r = new Proto\RespondActivityTaskCanceledRequest();
        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskToken($taskToken);

        if (func_num_args() == 2) {
            $r->setDetails(EncodedValues::fromValues([$details], $this->converter)->toPayloads());
        }

        try {
            $this->client->RespondActivityTaskCanceled($r);
        } catch (ServiceClientException $e) {
            // There is nothing that can be done at this point so let's just ignore.
        }
    }

    /**
     * {@inheritDoc}
     */
    public function recordHeartbeat(string $workflowId, ?string $runId, string $activityId, $details = null): void
    {
        $r = new Proto\RecordActivityTaskHeartbeatByIdRequest();
        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setWorkflowId($workflowId)
            ->setRunId($runId ?? '')
            ->setActivityId($activityId);

        if (func_num_args() == 4) {
            $r->setDetails(EncodedValues::fromValues([$details], $this->converter)->toPayloads());
        }

        try {
            $response = $this->client->RecordActivityTaskHeartbeatById($r);
            if ($response->getCancelRequested()) {
                throw new ActivityCanceledException();
            }
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw ActivityNotExistsException::fromPreviousWithActivityId($activityId, $e);
            }

            throw ActivityCompletionFailureException::fromPreviousWithActivityId($activityId, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function recordHeartbeatByToken(string $taskToken, $details = null): void
    {
        $r = new Proto\RecordActivityTaskHeartbeatRequest();
        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskToken($taskToken);

        if (func_num_args() == 2) {
            $r->setDetails(EncodedValues::fromValues([$details], $this->converter)->toPayloads());
        }

        try {
            $response = $this->client->RecordActivityTaskHeartbeat($r);
            if ($response->getCancelRequested()) {
                throw new ActivityCanceledException();
            }
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw ActivityNotExistsException::fromPrevious($e);
            }

            throw ActivityCompletionFailureException::fromPrevious($e);
        }
    }
}
