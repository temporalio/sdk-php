<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;

/**
 * Full control over a Nexus operation: one object owns both start and cancel.
 *
 * Return an implementation from an `#[AsyncOperation]` method with no parameters
 * to register a manual operation (e.g. backed by an external system). The
 * factory method is invoked once at worker registration.
 *
 * @template T The parameter type of the operation.
 * @template R The return type of the operation.
 */
interface OperationHandlerInterface
{
    /**
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
     * @throws HandlerException
     */
    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void;
}
