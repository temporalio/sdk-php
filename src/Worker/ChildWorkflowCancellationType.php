<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

/**
 * Defines behaviour of the parent workflow when {@see CancellationScope}
 * that wraps child workflow execution request is canceled. The result of
 * the cancellation independently of the type is
 * a {@see ChildWorkflowCancellationType} thrown from the child
 * workflow method.
 */
final class ChildWorkflowCancellationType
{
    /**
     * Wait for child cancellation completion.
     */
    public const WAIT_CANCELLATION_COMPLETED = 0x01;

    /**
     * Request cancellation of the child and wait for confirmation that the
     * request was received.
     *
     * Doesn't wait for actual cancellation.
     */
    public const WAIT_CANCELLATION_REQUESTED = 0x02;

    /**
     * Initiate a cancellation request and immediately report cancellation to the
     * parent. Note that it doesn't guarantee that cancellation is delivered to
     * the child if parent exits before the delivery is done. It can be mitigated
     * by setting {@see ParentClosePolicy} to {@see ParentClosePolicy#PARENT_CLOSE_POLICY_REQUEST_CANCEL}.
     */
    public const TRY_CANCEL = 0x03;

    /**
     * Do not request cancellation of the child workflow.
     */
    public const ABANDON = 0x04;
}
