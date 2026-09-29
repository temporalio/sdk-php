<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\Process;

final class SignalTrap
{
    /**
     * @param list<int> $signals
     * @param callable(int): void $handler
     * @return bool Whether the signals could be trapped at all
     */
    public static function trap(array $signals, callable $handler): bool
    {
        if (!\function_exists('pcntl_async_signals') || !\function_exists('pcntl_signal')) {
            return false;
        }

        \pcntl_async_signals(true);

        foreach ($signals as $signal) {
            \pcntl_signal($signal, $handler);
        }

        return true;
    }
}
