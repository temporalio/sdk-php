<?php

declare(strict_types=1);

namespace Temporal\Client\Update;

use Temporal\Internal\Traits\CloneWith;

final class UpdateOptions
{
    use CloneWith;

    public readonly ?string $updateId;
    public readonly ?string $firstExecutionRunId;
    public readonly WaitPolicy $waitPolicy;
    public readonly mixed $resultType;

    private function __construct(
        public readonly string $updateName,
        LifecycleStage $lifecycleStage,
    ) {
        $this->updateId = null;
        $this->firstExecutionRunId = null;
        $this->waitPolicy = WaitPolicy::new()->withLifecycleStage($lifecycleStage);
        $this->resultType = null;
    }

    /**
     * @param non-empty-string $updateName Name of the update handler. Usually it is a method name.
     * @param LifecycleStage $lifecycleStage Specifies at what point in the update request life cycles
     *        this request should return. By default, it is set to {@see LifecycleStage::StageAccepted} that
     *        means that the handle will return immediately after successful validation of the Update call.
     *        However, also note that the processing Workflow worker must be available. Otherwise, the request
     *        may block indefinitely or fail due to a timeout.
     *
     * @link https://docs.temporal.io/workflows#update
     */
    public static function new(string $updateName, LifecycleStage $lifecycleStage = LifecycleStage::StageAccepted): self
    {
        return new self($updateName, $lifecycleStage);
    }

    /**
     * Name of the update handler. Usually it is a method name.
     */
    public function withUpdateName(string $name): self
    {
        /** @see self::$updateName */
        return $this->cloneWith('updateName', $name);
    }

    /**
     * Specifies at what point in the update request life cycles this request should return.
     */
    public function withWaitPolicy(WaitPolicy $policy): self
    {
        /** @see self::$waitPolicy */
        return $this->cloneWith('waitPolicy', $policy);
    }

    /**
     * The update ID is an application-layer identifier for the requested update. It must be unique
     * within the scope of a workflow execution.
     */
    public function withUpdateId(?string $id): self
    {
        /** @see self::$updateId */
        return $this->cloneWith('updateId', $id);
    }

    /**
     * The RunID expected to identify the first run in the workflow execution chain. If this
     * expectation does not match then the server will reject the update request with an error.
     */
    public function withFirstExecutionRunId(?string $runId): self
    {
        /** @see self::$firstExecutionRunId */
        return $this->cloneWith('firstExecutionRunId', $runId);
    }

    /**
     * The type of the update return value.
     */
    public function withResultType(mixed $type): self
    {
        /** @see self::$resultType */
        return $this->cloneWith('resultType', $type);
    }
}
