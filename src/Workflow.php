<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Workflow\Runtime\Process;

/**
 * @mixin Process
 */
final class Workflow
{
    /**
     * @var Process
     */
    private static Process $process;

    /**
     * @param Process $process
     */
    public static function setCurrentProcess(Process $process): void
    {
        self::$process = $process;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $context = self::$process->getContext();

        return $context->$name(...$arguments);
    }
}
