<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Coroutine;

interface AppendableInterface extends CoroutineInterface
{
    /**
     * @param iterable $iterator
     * @param \Closure|null $then
     */
    public function push(iterable $iterator, \Closure $then = null): void;
}
