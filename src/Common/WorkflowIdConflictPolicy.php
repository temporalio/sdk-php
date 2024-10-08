<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

/**
 * Defines what to do when trying to start a workflow with the same workflow id as a *running* workflow.
 * Note that it is *never* valid to have two actively running instances of the same workflow id.
 *
 * @see IdReusePolicy for handling workflow id duplication with a *closed* workflow.
 * @see \Temporal\Api\Enums\V1\WorkflowIdConflictPolicy
 */
enum WorkflowIdConflictPolicy: int
{
    case Unspecified = 0;

    /**
     * Don't start a new workflow; instead return `WorkflowExecutionAlreadyStartedFailure`.
     */
    case Fail = 1;

    /**
     * Don't start a new workflow; instead return a workflow handle for the running workflow.
     */
    case UseExisting = 2;

    /**
     * Terminate the running workflow before starting a new one.
     */
    case TerminateExisting = 3;
}
