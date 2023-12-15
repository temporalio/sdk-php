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
 * Defines behaviour of the parent workflow when {@see CancellationScope} that
 * wraps child workflow execution request is canceled. The result of the
 * cancellation independently of the type is a {@see FailedCancellationException}
 * thrown from the child workflow method.
 *
 * @extends Type<bool>
 */
final class ActivityCancellationType extends Type
{
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
     */
    public const ABANDON = 0x02;

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current)
    {
        return $value ? self::WAIT_CANCELLATION_COMPLETED : self::TRY_CANCEL;
    }

    /**
     * {@inheritDoc}
     */
    public function serialize($value): bool
    {
        switch ($value) {
            case self::WAIT_CANCELLATION_COMPLETED:
                return true;

            case self::TRY_CANCEL:
                return false;

            default:
                $error = "Option #{$value} is currently not supported";
                throw new \InvalidArgumentException($error);
        }
    }
}
