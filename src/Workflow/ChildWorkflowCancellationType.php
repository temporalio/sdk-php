<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Exception\FailedCancellationException;
use Temporal\Internal\Marshaller\Type\Type;

/**
 * Defines behaviour of the parent workflow when {@see CancellationScope} that
 * wraps child workflow execution request is canceled. The result of the
 * cancellation independently of the type is a {@see FailedCancellationException}
 * thrown from the child workflow method.
 *
 * @psalm-type ChildWorkflowCancellationEnum = ChildWorkflowCancellationType::*
 * @extends Type<bool>
 */
final class ChildWorkflowCancellationType extends Type
{
    /**
     * Wait for child cancellation completion.
     */
    public const WAIT_CANCELLATION_COMPLETED = 0x00;

    /**
     * Request cancellation of the child and wait for confirmation that the
     * request was received.
     *
     * Doesn't wait for actual cancellation.
     */
    public const WAIT_CANCELLATION_REQUESTED = 0x01;

    /**
     * Initiate a cancellation request and immediately report cancellation to
     * the parent. Note that it doesn't guarantee that cancellation is delivered
     * to the child if parent exits before the delivery is done. It can be
     * mitigated by setting to {@see ParentClosePolicy::POLICY_REQUEST_CANCEL}
     */
    public const TRY_CANCEL = 0x02;

    /**
     * Do not request cancellation of the child workflow.
     */
    public const ABANDON  = 0x03;

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
    public function serialize($value)
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
