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
 * @psalm-type DateIntervalValue = string | int | float | \DateInterval
 * @psalm-type DateIntervalFormat = DateIntervalType::FORMAT_*
 */
final class DateInterval
{
    /**
     * @var string
     */
    private const ERROR_INVALID_DATETIME = 'Unrecognized date time interval format';

    /**
     * @var string
     */
    private const ERROR_INVALID_FORMAT = 'Invalid date interval format "%s", available formats: %s';

    /**
     * @var string
     */
    public const FORMAT_YEARS = 'y';

    /**
     * @var string
     */
    public const FORMAT_MONTHS = 'm';

    /**
     * @var string
     */
    public const FORMAT_WEEKS = 'weeks';

    /**
     * @var string
     */
    public const FORMAT_DAYS = 'days';

    /**
     * @var string
     */
    public const FORMAT_HOURS = 'hours';

    /**
     * @var string
     */
    public const FORMAT_MINUTES  = 'minutes';

    /**
     * @var string
     */
    public const FORMAT_SECONDS  = 'seconds';

    /**
     * @var string
     */
    public const FORMAT_MILLISECONDS = 'milliseconds';

    /**
     * @var string
     */
    public const FORMAT_MICROSECONDS = 'microseconds';

    /**
     * @var DateIntervalFormat[]
     */
    private const AVAILABLE_FORMATS = [
        self::FORMAT_YEARS,
        self::FORMAT_MONTHS,
        self::FORMAT_WEEKS,
        self::FORMAT_DAYS,
        self::FORMAT_HOURS,
        self::FORMAT_MINUTES,
        self::FORMAT_SECONDS,
        self::FORMAT_MILLISECONDS,
        self::FORMAT_MICROSECONDS,
    ];

    /**
     * @param string $format
     * @return string
     */
    private static function validateFormat(string $format): void
    {
        if (! isset(self::AVAILABLE_FORMATS[$format])) {
            $message = \sprintf(self::ERROR_INVALID_FORMAT, $format, \implode(', ', self::AVAILABLE_FORMATS));

            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * @param DateIntervalValue $interval
     * @param string $format
     * @return CarbonInterval
     */
    public static function parse($interval, string $format = self::FORMAT_MILLISECONDS): CarbonInterval
    {
        self::validateFormat($format);

        switch (true) {
            case \is_string($interval):
                return CarbonInterval::fromString($interval);

            case $interval instanceof \DateInterval:
                return CarbonInterval::instance($interval);

            case \is_int($interval):
            case \is_float($interval):
                return CarbonInterval::$format($interval);

            default:
                throw new \InvalidArgumentException(self::ERROR_INVALID_DATETIME);
        }
    }

    /**
     * @param DateIntervalValue $interval
     * @return bool
     */
    public static function assert($interval): bool
    {
        $isParsable = \is_string($interval) || \is_int($interval) || \is_float($interval);

        return $isParsable || $interval instanceof \DateInterval;
    }
}
