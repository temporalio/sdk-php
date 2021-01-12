<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdRequest;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest;
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
use Temporal\Worker\Transport\RpcConnectionInterface;

class ActivityCompletionClient implements ActivityCompletionClientInterface
{
    /**
     * @var ServiceClientInterface|RpcConnectionInterface
     */
    private $serviceClient;
    /**
     * @var ClientOptions
     */
    private ClientOptions $clientOptions;

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $converter;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions $clientOptions
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(
        ServiceClientInterface $serviceClient,
        ClientOptions $clientOptions,
        DataConverterInterface $dataConverter
    ) {
        $this->serviceClient = $serviceClient;
        $this->clientOptions = $clientOptions;
        $this->converter = $dataConverter;
    }

    /**
     * {@inheritDoc}
     */
    public function complete(string $workflowId, ?string $runId, string $activityId, $result = null): void
    {
        $r = new RespondActivityTaskCompletedByIdRequest();
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
            $this->serviceClient->RespondActivityTaskCompletedById($r);
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
        $r = new RespondActivityTaskCompletedRequest();

        $r
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskToken($taskToken);

        $input = EncodedValues::fromValues(array_slice(func_get_args(), 1), $this->converter);
        if (!$input->isEmpty()) {
            $r->setResult($input->toPayloads());
        }

        try {
            $this->serviceClient->RespondActivityTaskCompleted($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw ActivityNotExistsException::fromPrevious($e);
            }

            throw  ActivityCompletionFailureException::fromPrevious($e);
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
        $r = new RespondActivityTaskFailedByIdRequest();
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);
        $r->setWorkflowId($workflowId);
        $r->setRunId($runId ?? '');
        $r->setActivityId($activityId);

        $r->setFailure(FailureConverter::mapExceptionToFailure($error, $this->converter));

        try {
            $this->serviceClient->RespondActivityTaskFailedById($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                // todo: map
                throw new ActivityNotExistsException();
            }

            // todo: map
            throw new ActivityCompletionFailureException();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function completeExceptionallyByToken(string $taskToken, \Throwable $error): void
    {
        $r = new RespondActivityTaskFailedRequest();
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);
        $r->setTaskToken($taskToken);

        $r->setFailure(FailureConverter::mapExceptionToFailure($error, $this->converter));

        try {
            $this->serviceClient->RespondActivityTaskFailed($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                // todo: map
                throw new ActivityNotExistsException();
            }

            // todo: map
            throw new ActivityCompletionFailureException();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function reportCancellation(string $workflowId, ?string $runId, string $activityId, $details = null): void
    {
        $r = new RespondActivityTaskCanceledByIdRequest();
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);
        $r->setWorkflowId($workflowId);
        $r->setRunId($runId ?? '');
        $r->setActivityId($activityId);

        if (func_num_args() == 4) {
            $r->setDetails(EncodedValues::fromValues([$details], $this->converter)->toPayloads());
        }

        try {
            $this->serviceClient->RespondActivityTaskCanceledById($r);
        } catch (ServiceClientException $e) {
            // There is nothing that can be done at this point so let's just ignore.
            // todo: confirm this is true
        }
    }

    /**
     * {@inheritDoc}
     */
    public function reportCancellationByToken(string $taskToken, $details = null): void
    {
        $r = new RespondActivityTaskCanceledRequest();
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);
        $r->setTaskToken($taskToken);


        if (func_num_args() == 2) {
            $r->setDetails(EncodedValues::fromValues([$details], $this->converter)->toPayloads());
        }

        try {
            $this->serviceClient->RespondActivityTaskCanceled($r);
        } catch (ServiceClientException $e) {
            // There is nothing that can be done at this point so let's just ignore.
            // todo: confirm this is true
        }
    }

    /**
     * {@inheritDoc}
     */
    public function recordHeartbeat(string $workflowId, ?string $runId, string $activityId, $details = null): bool
    {
        $r = new RecordActivityTaskHeartbeatByIdRequest();
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);
        $r->setWorkflowId($workflowId);
        $r->setRunId($runId ?? '');
        $r->setActivityId($activityId);

        if (func_num_args() == 4) {
            $r->setDetails(EncodedValues::fromValues([$details], $this->converter)->toPayloads());
        }

        try {
            $response = $this->serviceClient->RecordActivityTaskHeartbeatById($r);
            if ($response->getCancelRequested()) {
                throw new ActivityCanceledException();
            }
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                // todo: map
                throw new ActivityNotExistsException();
            }

            // todo: why this exception type?
            throw new ActivityCompletionFailureException();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function recordHeartbeatByToken(string $taskToken, $details = null): bool
    {
        $r = new RecordActivityTaskHeartbeatRequest();
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);
        $r->setTaskToken($taskToken);

        if (func_num_args() == 2) {
            $r->setDetails(EncodedValues::fromValues([$details], $this->converter)->toPayloads());
        }

        try {
            $response = $this->serviceClient->RecordActivityTaskHeartbeat($r);
            if ($response->getCancelRequested()) {
                throw new ActivityCanceledException();
            }
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                // todo: map
                throw new ActivityNotExistsException();
            }

            // todo: why this exception type?
            throw new ActivityCompletionFailureException();
        }
    }
}