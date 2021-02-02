<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * @codeCoverageIgnore
 */
class Process
{
    /**
     * Run process with arguments and get result.
     *
     * @param string ...$cmd
     * @return string
     */
    public static function run(string ...$cmd): string
    {
        $process = new \Symfony\Component\Process\Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return trim($process->getOutput());
    }
}
