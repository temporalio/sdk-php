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
 * Listener for handler-method cancellation (not Nexus operation cancel).
 * Object form (not Closure) so it can be removed by identity. Must be
 * non-blocking and must not throw.
 */
interface MethodCancellationListenerInterface
{
    /**
     * Invoked exactly once. To get the reason, read it from the owning
     * {@see MethodCanceller}.
     */
    public function cancelled(): void;
}
