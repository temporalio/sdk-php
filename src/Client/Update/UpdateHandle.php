<?php

declare(strict_types=1);

namespace Temporal\Client\Update;

use Temporal\Api\Workflowservice\V1\PollWorkflowExecutionUpdateRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Interceptor\WorkflowClient\UpdateInput;
use Temporal\Interceptor\WorkflowClient\UpdateRef;
use Temporal\Workflow\WorkflowExecution;

/**
 * UpdateHandle is a handle to an update workflow execution request that can be used to get the
 * status of that update request.
 */
final class UpdateHandle
{
    public function __construct(
        private readonly ServiceClientInterface $client,
        private readonly ClientOptions $clientOptions,
        private readonly UpdateInput $updateInput,
        private readonly UpdateRef $updateRef,
        private ValuesInterface|WorkflowUpdateException|null $result,
    ) {
    }


    /**
     * Gets the workflow execution this update request was sent to.
     */
    public function getExecution(): WorkflowExecution
    {
        return $this->updateRef->workflowExecution;
    }

    /**
     * Gets the unique ID of this update.
     */
    public function getId(): string
    {
        return $this->updateRef->updateId;
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
     * @throws WorkflowUpdateException
     * @throws TimeoutException
     */
    public function getResult(): mixed
    {
        return $this->getEncodedValues()->getValue(0, $this->updateInput->resultType);
    }

    /**
     * Fetch and return the encoded result of this update request.
     *
     * @throws WorkflowUpdateException
     * @throws TimeoutException
     */
    public function getEncodedValues(): ValuesInterface
    {
        if ($this->result === null) {
            $this->fetchResult();
        }

        return $this->result instanceof WorkflowUpdateException
            ? throw $this->result
            : $this->result;
    }

    /**
     * @psalm-assert !null $this->result
     * @throws TimeoutException
     */
    private function fetchResult(): void
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
        );

        // Workflow Uprate accepted
        $result = $response->getOutcome();
        \assert($result !== null);

        // Accepted with result
        if ($result->getSuccess() !== null) {
            $this->result = $result->getSuccess();
            return;
        }

        // Accepted with failure
        $failure = $result->getFailure();
        \assert($failure instanceof \Throwable);

        $this->result = new WorkflowUpdateException(
            $failure->getMessage(),
            execution: $this->getExecution(),
            workflowType: $this->updateInput->workflowType,
            updateId: $this->getId(),
            updateName: $this->updateInput->updateName,
            previous: $failure,
        );
    }
}
