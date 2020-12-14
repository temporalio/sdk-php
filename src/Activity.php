<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Activity\ActivityInfo;
use Temporal\Client\Internal\Support\Facade;

/**
 * @method static ActivityInfo getInfo()
 * @method static array getArguments()
 *
 * @method static mixed heartbeat(mixed $details)
 *
 * @method static void doNotCompleteOnReturn()
 * @method static bool isDoNotCompleteOnReturn()
 */
final class Activity extends Facade
{
}
