<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Support;

use Carbon\CarbonInterval;

/**
 * @psalm-type DateIntervalFormat = string|int|float|\DateInterval
 */
final class DateInterval
{
    /**
     * @var string
     */
    private const ERROR_UNRECOGNIZED_TYPE = 'Unrecognized date time interval format';

    /**
     * @psalm-param DateIntervalFormat $interval
     *
     * @param string|int|float|\DateInterval $interval
     * @return int
     * @throws \Exception
     */
    public static function parse($interval): int
    {
        switch (true) {
            case \is_string($interval):
                $interval = CarbonInterval::fromString($interval);

            case $interval instanceof \DateInterval:
                return (int)($interval->f * 1000);

            case \is_int($interval):
                return $interval * 1000;

            case \is_float($interval):
                return (int)($interval * 1000);

            default:
                throw new \InvalidArgumentException(self::ERROR_UNRECOGNIZED_TYPE);
        }
    }
}
