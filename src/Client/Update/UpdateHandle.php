<?php

declare(strict_types=1);

namespace Temporal\Client\Update;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * UpdateHandle is a handle to an update workflow execution request that can be used to get the
 * status of that update request.
 */
final class UpdateHandle
{
    public function __construct(
        private readonly WorkflowExecution $execution,
        private readonly string $id,
        private readonly ?ValuesInterface $result,
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
        return $this->id;
    }

    public function getResult(): ?ValuesInterface
    {
        return $this->result;
    }
}
