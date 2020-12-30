<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Coroutine;

class Coroutine
{
    /**
     * @param iterable $iterable
     * @return CoroutineInterface
     */
    public static function create(iterable $iterable): CoroutineInterface
    {
        if ($iterable instanceof CoroutineInterface) {
            return $iterable;
        }

        return new Decorator($iterable);
    }
}
