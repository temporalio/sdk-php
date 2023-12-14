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
 * Defines how new runs of a workflow with a particular ID may or may not be allowed. Note that
 * it is *never* valid to have two actively running instances of the same workflow id.
 *
 * @psalm-type IdReusePolicyEnum = IdReusePolicy::POLICY_*
 *
 * @see \Temporal\Api\Enums\V1\WorkflowIdReusePolicy
 */
enum IdReusePolicy: int
{
    case Unspecified = 0;

    /**
     * Allow starting a workflow execution using the same workflow id.
     */
    case AllowDuplicate = 1;

    /**
     * Allow starting a workflow execution using the same workflow id, only when the last
     * execution's final state is one of [terminated, cancelled, timed out, failed].
     */
    case AllowDuplicateFailedOnly = 2;

    /**
     * Do not permit re-use of the workflow id for this workflow. Future start workflow requests
     * could potentially change the policy, allowing re-use of the workflow id.
     */
    case RejectDuplicate = 3;

    /**
     * If a workflow is running using the same workflow ID, terminate it and start a new one.
     * If no running workflow, then the behavior is the same as ALLOW_DUPLICATE
     */
    case TerminateIfRunning = 4;

    /**
     * @var int
     */
    public const POLICY_UNSPECIFIED = 0;

    /**
     * Allow start a workflow execution using the same workflow Id, when
     * workflow not running.
     */
    public const POLICY_ALLOW_DUPLICATE = 1;

    /**
     * Allow start a workflow execution using the same workflow Id, when
     * workflow not running, and the last execution close state is in
     * [terminated, cancelled, timed out, failed].
     */
    public const POLICY_ALLOW_DUPLICATE_FAILED_ONLY = 2;

    /**
     * Do not allow start a workflow execution using the same workflow
     * Id at all.
     */
    public const POLICY_REJECT_DUPLICATE = 3;

    /**
     * Do not allow start a workflow execution using the same workflow
     * Id at all.
     */
    public const POLICY_TERMINATE_IF_RUNNING = 4;
}
