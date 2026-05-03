<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

/**
 * Middleware for intercepting operations.
 */
interface OperationMiddlewareInterface
{
    /**
     * Intercepts the given operation. Called once for each operation invocation.
     */
    public function intercept(
        OperationContext $context,
        OperationHandlerInterface $next,
    ): OperationHandlerInterface;
}
