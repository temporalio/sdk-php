<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use Temporal\Activity\ActivityContextInterface;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\Type;
use Temporal\Exception\OutOfContextException;
use Temporal\Internal\Support\Facade;

/**
 * @method static array getArguments()
 *
 * @method static bool isDoNotCompleteOnReturn()
 *
 * @template-extends Facade<ActivityContextInterface>
 */
final class Activity extends Facade
{
    /**
     * Returns information about current workflow execution.
     *
     * @return ActivityInfo
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function getInfo(): ActivityInfo
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

    /**
     * @return bool
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function hasHeartbeatDetails(): bool
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

    /**
     * @param null $type
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function getHeartbeatDetails($type = null)
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

    /**
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function doNotCompleteOnReturn(): void
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

    /**
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function heartbeat($details): void
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

}
