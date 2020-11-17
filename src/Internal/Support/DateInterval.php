<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Support;

use Carbon\CarbonInterval;

/**
 * @psalm-type DateIntervalFormat = string | int | float | \DateInterval
 */
final class DateInterval
{
    /**
     * @var string
     */
    private const ERROR_UNRECOGNIZED_TYPE = 'Unrecognized date time interval format';

    /**
     * @param DateIntervalFormat $interval
     * @return \DateInterval
     * @throws \Exception
     */
    public static function parse($interval): \DateInterval
    {
        switch (true) {
            case \is_string($interval):
                return CarbonInterval::fromString($interval);

            case $interval instanceof \DateInterval:
                return $interval;

            case \is_int($interval):
            case \is_float($interval):
                return CarbonInterval::seconds($interval);

            default:
                throw new \InvalidArgumentException(self::ERROR_UNRECOGNIZED_TYPE);
        }
    }

    /**
     * @param DateIntervalFormat $interval
     * @return bool
     */
    public static function assert($interval): bool
    {
        $isParsable = \is_string($interval) || \is_int($interval) || \is_float($interval);

        return $isParsable || $interval instanceof \DateInterval;
    }
}
