<?php

declare(strict_types=1);

namespace Temporal\Client\Update;

use Temporal\Api\Workflowservice\V1\PollWorkflowExecutionUpdateRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Workflow\WorkflowExecution;

/**
 * UpdateHandle is a handle to an update workflow execution request that can be used to get the
 * status of that update request.
 */
final class UpdateHandle
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ServiceClientInterface $client,
        private readonly ClientOptions $clientOptions,
        private readonly DataConverterInterface $converter,
        private readonly WorkflowExecution $execution,
        private readonly ?string $workflowType,
        private readonly string $updateName,
        private readonly mixed $resultType,
        private readonly string $updateId,
        private ValuesInterface|WorkflowUpdateException|null $result,
    ) {
    }


    /**
     * Gets the workflow execution this update request was sent to.
     */
    public function getExecution(): WorkflowExecution
    {
        return $this->execution;
    }

    /**
     * Gets the unique ID of this update.
     */
    public function getId(): string
    {
        return $this->updateId;
    }

    /**
     * Check there is a cached accepted result or failure for this update request.
     *
     * @return bool
     */
    public function hasResult(): bool
    {
        return $this->result !== null;
    }

    /**
     * Fetch and decode the result of this update request.
     *
     * @param int|float|null $timeout Timeout in seconds. Accuracy to milliseconds.
     *
     * @throws WorkflowUpdateException
     * @throws TimeoutException
     */
    public function getResult(int|float|null $timeout = null): mixed
    {
        return $this->getEncodedValues($timeout)->getValue(0, $this->resultType);
    }

    /**
     * Fetch and return the encoded result of this update request.
     *
     * @param int|float|null $timeout Timeout in seconds. Accuracy to milliseconds.
     *
     * @throws WorkflowUpdateException
     * @throws TimeoutException
     */
    public function getEncodedValues(int|float|null $timeout = null): ValuesInterface
    {
        if ($this->result === null) {
            $this->fetchResult($timeout);
        }

        return $this->result instanceof WorkflowUpdateException
            ? throw $this->result
            : $this->result;
    }

    /**
     * @param int|float|null $timeout Timeout in seconds. Accuracy to milliseconds.
     *
     * @psalm-assert !null $this->result
     * @throws TimeoutException
     */
    private function fetchResult(int|float|null $timeout = null): void
    {
        $request = (new PollWorkflowExecutionUpdateRequest())
            ->setUpdateRef(
                (new \Temporal\Api\Update\V1\UpdateRef())
                    ->setUpdateId($this->getId())
                    ->setWorkflowExecution($this->getExecution()->toProtoWorkflowExecution())
            )
            ->setNamespace($this->clientOptions->namespace)
            ->setIdentity($this->clientOptions->identity)
            ->setWaitPolicy(
                (new \Temporal\Api\Update\V1\WaitPolicy())->setLifecycleStage(LifecycleStage::StageCompleted->value)
            );

        $response = $this->client->PollWorkflowExecutionUpdate(
            $request,
            $timeout === null ? null : $this->client->getContext()->withTimeout($timeout),
        );

        // Workflow Uprate accepted
        $result = $response->getOutcome();
        \assert($result !== null);

        // Accepted with result
        if ($result->getSuccess() !== null) {
            $this->result = EncodedValues::fromPayloads($result->getSuccess(), $this->converter);
            return;
        }

        // Accepted with failure
        $failure = $result->getFailure();
        \assert($failure !== null);
        $e = FailureConverter::mapFailureToException($failure, $this->converter);

        $this->result = new WorkflowUpdateException(
            $e->getMessage(),
            execution: $this->getExecution(),
            workflowType: $this->workflowType,
            updateId: $this->getId(),
            updateName: $this->updateName,
            previous: $e,
        );
    }
}
