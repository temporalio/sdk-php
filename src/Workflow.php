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
 * TODO Static class with current execution workflow context
 */
final class Workflow
{
    private static Process $process;

    public static function setCurrentProcess(Process $process): void
    {

    }
}
