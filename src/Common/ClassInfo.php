<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Common;

final class ClassInfo
{
    /**
     * @param $classOrObject
     * @return string
     */
    public static function name($classOrObject): string
    {
        $fqn = \is_object($classOrObject) ? \get_class($classOrObject) : $classOrObject;

        $chunks = \explode('\\', $fqn);

        return \array_pop($chunks);
    }
}
