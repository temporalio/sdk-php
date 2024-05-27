<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Dispatcher;

/**
 * @internal
 */
interface DispatcherInterface
{
    public function dispatch(object $ctx, array $arguments): mixed;

    /**
     * @return array<\ReflectionType>
     */
    public function getArgumentTypes(): array;
}
