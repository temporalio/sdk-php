<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda;

final class Clock
{
    public static function nowMs(): int
    {
        return (int) (\microtime(true) * 1000.0);
    }
}
