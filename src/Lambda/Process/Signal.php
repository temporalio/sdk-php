<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\Process;

final class Signal
{
    public const SIGTERM = 15;
    public const SIGKILL = 9;
    public const SIGINT = 2;
}
