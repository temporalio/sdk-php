<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Temporal\Client\Update\LifecycleStage;
use Temporal\Workflow\WorkflowExecution;

/**
 * Indicates that a Workflow Update has no result yet.
 */
final class WorkflowUpdateResultException extends WorkflowException
{
    public function __construct(
        private readonly ?LifecycleStage $stage,
        WorkflowExecution $execution,
        string $workflowType,
        private readonly string $updateId,
        private readonly string $updateName,
    ) {
        $message = match ($stage) {
            LifecycleStage::StageAdmitted => \sprintf(
                "Update `%s` has not yet been accepted or rejected by the Workflow `%s`.",
                $updateName,
                $workflowType,
            ),
            default => \sprintf("Update `%s` has no result.", $updateName),
        };

        parent::__construct($message, $execution, $workflowType);
    }

    public function getUpdateId(): string
    {
        return $this->updateId;
    }

    public function getUpdateName(): string
    {
        return $this->updateName;
    }

    public function getStage(): LifecycleStage
    {
        return $this->stage ?? LifecycleStage::StageUnspecified;
    }
}
