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

/**
 * Handler for an operation.
 *
 * @template T The parameter type of the operation.
 * @template R The return type of the operation.
 */
interface OperationHandlerInterface
{
    /**
     * Handle the start of an operation.
     *
     * @param T|null $param
     * @return OperationStartResult<R>
     *
     * @throws OperationException
     * @throws HandlerException
     */
    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult;

    /**
     * Cancel the asynchronously started operation.
     *
     * Per Nexus spec cancellation is idempotent: ignore repeat cancels for the
     * same token (including cancels of an already-terminal operation). Throw
     * {@see HandlerException} only for genuine routing/permission/transport
     * errors.
     *
     * @throws HandlerException
     */
    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void;
}
