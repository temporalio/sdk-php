<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;

/**
 * Top-level handler for service calls.
 */
interface HandlerInterface
{
    /**
     * Handle the start of an operation.
     *
     * @return OperationStartResult<HandlerResultContent>
     *
     * @throws OperationException
     * @throws HandlerException
     */
    public function startOperation(
        OperationContext $context,
        OperationStartDetails $details,
        HandlerInputContent $input,
    ): OperationStartResult;

    /**
     * Cancel the asynchronously started operation.
     *
     * Per Nexus spec, cancellation is **idempotent**: implementations must
     * ignore repeat cancel requests for the same operation token (including
     * cancels for an operation that has already reached a terminal state) and
     * return successfully. Throw {@see HandlerException} only for genuine
     * routing/permission/transport errors, never for "already cancelled" or
     * "already completed".
     *
     * @throws HandlerException
     */
    public function cancelOperation(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void;
}
