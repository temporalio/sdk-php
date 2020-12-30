<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

/**
 * @psalm-type DispatchableHandler = callable(array): mixed
 */
interface InstanceInterface
{
    /**
     * @return DispatchableHandler
     */
    public function getHandler(): callable;

    /**
     * @return object|null
     */
    public function getContext(): ?object;
}
