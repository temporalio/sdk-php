<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Temporal\Exception\FailedCancellationException;
use Temporal\Internal\Marshaller\Type\Type;

/**
 * Defines behaviour of the parent workflow when `CancellationScope` that
 * wraps child workflow execution request is canceled.
 *
 * The result of the cancellation independently of the type is a {@see FailedCancellationException}
 * thrown from the child workflow method.
 */
enum ActivityCancellationType: int
{
    /**
     * Wait for activity cancellation completion. Note that activity must
     * heartbeat to receive a cancellation notification. This can block the
     * cancellation for a long time if activity doesn't heartbeat or chooses to
     * ignore the cancellation request.
     */
    case WaitCancellationCompleted = 0;

    /**
     * Initiate a cancellation request and immediately report cancellation to
     * the workflow.
     */
    case TryCancel = 1;

    /**
     * Do not request cancellation of the activity and immediately report
     * cancellation to the workflow.
     *
     * Note: currently not supported.
     */
    case Abandon = 2;

    /**
     * Wait for activity cancellation completion. Note that activity must
     * heartbeat to receive a cancellation notification. This can block the
     * cancellation for a long time if activity doesn't heartbeat or chooses to
     * ignore the cancellation request.
     */
    public const WAIT_CANCELLATION_COMPLETED = 0x00;

    /**
     * Initiate a cancellation request and immediately report cancellation to
     * the workflow.
     */
    public const TRY_CANCEL = 0x01;

    /**
     * Do not request cancellation of the activity and immediately report
     * cancellation to the workflow.
     *
     * Note: currently not supported.
     */
    public const ABANDON = 0x02;
}
