<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception;

use Temporal\Api\Failure\V1\Failure;

class WorkflowExecutionFailedException extends TemporalException
{
    private Failure $failure;
    private int $lastWorkflowTaskCompletedEventId;
    private int $retryState;

    /**
     * WorkflowExecutionFailedException constructor.
     */
    public function __construct(Failure $failure, int $lastWorkflowTaskCompletedEventId, int $retryState)
    {
        parent::__construct('execution failed');
        $this->failure = $failure;
        $this->lastWorkflowTaskCompletedEventId = $lastWorkflowTaskCompletedEventId;
        $this->retryState = $retryState;
    }

    public function getFailure(): Failure
    {
        return $this->failure;
    }

    public function getWorkflowTaskCompletedEventId(): int
    {
        return $this->lastWorkflowTaskCompletedEventId;
    }

    public function getRetryState(): int
    {
        return $this->retryState;
    }
}
