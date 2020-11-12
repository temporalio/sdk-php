<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Activity\ActivityContextInterface;
use Temporal\Client\Activity\ActivityInfo;

/**
 * Class Activity
 *
 * @method static ActivityInfo getInfo()
 */
final class Activity
{
    /**
     * @var string
     */
    private const ERROR_NO_ACTIVITY_CONTEXT =
        'Calling activity methods can only be made from ' .
        'the currently running activity process';

    /**
     * @var ActivityContextInterface|null
     */
    private static ?ActivityContextInterface $ctx = null;

    /**
     * @param ActivityContextInterface|null $ctx
     */
    public static function setCurrentContext(?ActivityContextInterface $ctx): void
    {
        self::$ctx = $ctx;
    }

    /**
     * @return ActivityContextInterface
     */
    private static function getCurrentContext(): ActivityContextInterface
    {
        if (self::$ctx === null) {
            throw new \RuntimeException(self::ERROR_NO_ACTIVITY_CONTEXT);
        }

        return self::$ctx;
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $context = self::getCurrentContext();

        return $context->$name(...$arguments);
    }
}
