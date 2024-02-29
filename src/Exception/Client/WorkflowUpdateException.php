<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Temporal\Workflow\WorkflowExecution;

final class WorkflowUpdateException extends WorkflowException
{
    public function __construct(
        ?string $message,
        WorkflowExecution $execution,
        ?string $workflowType,
        private readonly string $updateId,
        private readonly string $updateName,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $execution, $workflowType, $previous);
    }

    public function getUpdateId(): string
    {
        return $this->updateId;
    }

    public function getUpdateName(): string
    {
        return $this->updateName;
    }
}
