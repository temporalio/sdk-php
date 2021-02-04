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
    /**
     * @var Failure
     */
    private Failure $failure;

    /**
     * @var int
     */
    private int $lastWorkflowTaskCompletedEventId;

    /**
     * @var int
     */
    private int $retryState;

    /**
     * WorkflowExecutionFailedException constructor.
     * @param Failure $failure
     * @param int $lastWorkflowTaskCompletedEventId
     * @param int $retryState
     */
    public function __construct(Failure $failure, int $lastWorkflowTaskCompletedEventId, int $retryState)
    {
        parent::__construct('execution failed');
        $this->failure = $failure;
        $this->lastWorkflowTaskCompletedEventId = $lastWorkflowTaskCompletedEventId;
        $this->retryState = $retryState;
    }

    /**
     * @return Failure
     */
    public function getFailure(): Failure
    {
        return $this->failure;
    }

    /**
     * @return int
     */
    public function getWorkflowTaskCompletedEventId(): int
    {
        return $this->lastWorkflowTaskCompletedEventId;
    }

    /**
     * @return int
     */
    public function getRetryState(): int
    {
        return $this->retryState;
    }
}
