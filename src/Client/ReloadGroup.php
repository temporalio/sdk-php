<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

/**
 * @psalm-type ReloadGroupFlags = ReloadGroup::GROUP_*
 */
final class ReloadGroup
{
    /**
     * @var int
     */
    public const GROUP_ACTIVITIES = 0x01;

    /**
     * @var int
     */
    public const GROUP_WORKFLOWS = 0x02;

    /**
     * @var int
     */
    public const GROUP_ALL = self::GROUP_ACTIVITIES
                           | self::GROUP_WORKFLOWS
    ;
}
