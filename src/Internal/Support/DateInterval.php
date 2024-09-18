<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

use Carbon\CarbonInterval;
use Google\Protobuf\Duration;

/**
 * @psalm-type DateIntervalFormat = DateInterval::FORMAT_*
 * @psalm-type DateIntervalValue = string | int | float | \DateInterval
 */
final class DateInterval
{
    public const FORMAT_YEARS = 'years';
    public const FORMAT_MONTHS = 'months';
    public const FORMAT_WEEKS = 'weeks';
    public const FORMAT_DAYS = 'days';
    public const FORMAT_HOURS = 'hours';
    public const FORMAT_MINUTES = 'minutes';
    public const FORMAT_SECONDS = 'seconds';
    public const FORMAT_MILLISECONDS = 'milliseconds';
    public const FORMAT_MICROSECONDS = 'microseconds';
    public const FORMAT_NANOSECONDS = 'nanoseconds';
    private const ERROR_INVALID_DATETIME = 'Unrecognized date time interval format';
    private const ERROR_INVALID_FORMAT = 'Invalid date interval format "%s", available formats: %s';
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
        self::FORMAT_NANOSECONDS,
    ];

    /**
     * @param DateIntervalValue $interval
     * @param DateIntervalFormat $format
     * @return CarbonInterval
     * @psalm-suppress InvalidOperand
     */
    public static function parse($interval, string $format = self::FORMAT_MILLISECONDS): CarbonInterval
    {
        switch (true) {
            case \is_string($interval):
                return CarbonInterval::fromString($interval);

            case $interval instanceof \DateInterval:
                return CarbonInterval::instance($interval);

            case \is_int($interval):
            case \is_float($interval):
                self::validateFormat($format);

                $int = (int) \floor($interval);
                $fraction = $interval - $int;

                $micros = match ($format) {
                    self::FORMAT_NANOSECONDS => $interval / 1_000,
                    self::FORMAT_MICROSECONDS => $int,
                    self::FORMAT_MILLISECONDS => $interval * 1_000,
                    default => $fraction > 0 ? match ($format) {
                        self::FORMAT_SECONDS => $fraction * 1_000_000,
                        self::FORMAT_MINUTES => $fraction * 60_000_000,
                        default => 0,
                    } : 0,
                };

                $seconds = \floor($micros / 1_000_000) + \floor(match ($format) {
                    self::FORMAT_SECONDS => $int,
                    self::FORMAT_MINUTES => $int * 60,
                    self::FORMAT_HOURS => $int * 3600,
                    self::FORMAT_DAYS => $int * 86400,
                    self::FORMAT_WEEKS => $int * 604800,
                    default => 0,
                });

                $minutes = (int) \floor($seconds / 60);
                $hours = (int) \floor($minutes / 60);
                $days = (int) \floor($hours / 24);

                return CarbonInterval::create(
                    years: 0,
                    weeks: (int) \floor($days / 7),
                    days: $days % 7,
                    hours: $hours % 24,
                    minutes: $minutes % 60,
                    seconds: $seconds % 60,
                    microseconds: $micros % 1000_000,
                );
            default:
                throw new \InvalidArgumentException(self::ERROR_INVALID_DATETIME);
        }
    }

    /**
     * @param DateIntervalValue|null $interval
     * @param DateIntervalFormat $format
     * @return CarbonInterval|null
     */
    public static function parseOrNull($interval, string $format = self::FORMAT_MILLISECONDS): ?CarbonInterval
    {
        if ($interval === null) {
            return null;
        }

        return self::parse($interval, $format);
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

    /**
     * @param \DateInterval|null $i
     * @return Duration|null
     */
    public static function toDuration(\DateInterval $i = null): ?Duration
    {
        if ($i === null) {
            return null;
        }

        $d = new Duration();
        $parsed = self::parse($i);
        $d->setSeconds((int) $parsed->totalSeconds);
        $d->setNanos($parsed->microseconds * 1000);

        return $d;
    }

    /**
     * @param string $format
     * @return void
     */
    private static function validateFormat(string $format): void
    {
        if (!\in_array($format, self::AVAILABLE_FORMATS, true)) {
            $message = \sprintf(self::ERROR_INVALID_FORMAT, $format, \implode(', ', self::AVAILABLE_FORMATS));

            throw new \InvalidArgumentException($message);
        }
    }
}
