<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Exception\OperationException;

/**
 * Function contract for synchronous operations.
 *
 * Preferred over a bare `callable` because it lets IDEs surface the exact
 * signature, enables static analysis of the parameter/return types, and
 * allows passing named DI-managed functors.
 *
 * @template T
 * @template R
 */
interface SynchronousOperationFunctionInterface
{
    /**
     * @param T|null $input
     * @return R|null
     *
     * @throws OperationException
     */
    public function __invoke(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $input,
    ): mixed;
}
