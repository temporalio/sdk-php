<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Support;

final class Iter
{
    /**
     * @param \Traversable|array $iterable
     * @return array
     */
    public static function toArray(iterable $iterable): array
    {
        return $iterable instanceof \Traversable ? \iterator_to_array($iterable) : $iterable;
    }
}
