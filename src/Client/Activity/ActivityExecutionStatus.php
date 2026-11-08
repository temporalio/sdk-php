<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

/**
 * @see \Temporal\Api\Enums\V1\ActivityExecutionStatus
 */
enum ActivityExecutionStatus: int
{
    case Unspecified = 0;
    case Running = 1;
    case Completed = 2;
    case Failed = 3;
    case Canceled = 4;
    case Terminated = 5;
    case TimedOut = 6;
    case Paused = 7;
}
