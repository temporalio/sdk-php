<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\NexusOperationContext;

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
        ?NexusOperationContext $nexusOperation = null,
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
        ?NexusOperationContext $nexusOperation = null,
    ): void;
}
