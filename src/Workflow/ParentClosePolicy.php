<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

enum ParentClosePolicy: int
{
    case Unspecified = 0;

    /**
     * Terminate means terminating the child workflow.
     */
    case Terminate = 1;

    /**
     * Abandon means not doing anything on the child workflow.
     */
    case Abandon = 2;

    /**
     * Cancel means requesting cancellation on the child workflow.
     */
    case RequestCancel = 3;

    /**
     * @var int
     */
    public const POLICY_UNSPECIFIED = 0;

    /**
     * Terminate means terminating the child workflow.
     */
    public const POLICY_TERMINATE = 1;

    /**
     * Abandon means not doing anything on the child workflow.
     */
    public const POLICY_ABANDON = 2;

    /**
     * Cancel means requesting cancellation on the child workflow.
     */
    public const POLICY_REQUEST_CANCEL = 3;
}
