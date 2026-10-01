<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
use Temporal\Internal\Support\DateInterval;

final class ProtoTime
{
    private const NANOS_PER_SECOND = 1_000_000_000;
    private const NANOS_PER_MICRO = 1_000;
    private const MICROS_PER_SECOND = 1_000_000;
    private const INTERVAL_CACHE_SIZE = 64;

    /** @var array<int, CarbonInterval> */
    private static array $intervals = [];

    public static function nanos(?Duration $duration): int
    {
        return $duration === null ? 0 : $duration->getSeconds() * self::NANOS_PER_SECOND + $duration->getNanos();
    }

    public static function duration(int $nanos): Duration
    {
        return new Duration(['seconds' => \intdiv($nanos, self::NANOS_PER_SECOND), 'nanos' => $nanos % self::NANOS_PER_SECOND]);
    }

    public static function optionalDuration(int|array|null $value): ?Duration
    {
        if (\is_array($value)) {
            return new Duration(['seconds' => (int) ($value['seconds'] ?? 0), 'nanos' => (int) ($value['nanos'] ?? 0)]);
        }

        return $value > 0 ? self::duration($value) : null;
    }

    public static function interval(?Duration $duration): CarbonInterval
    {
        $nanos = self::nanos($duration);
        if (!isset(self::$intervals[$nanos])) {
            if (\count(self::$intervals) >= self::INTERVAL_CACHE_SIZE) {
                self::$intervals = [];
            }
            self::$intervals[$nanos] = DateInterval::parse(self::nanos($duration), DateInterval::FORMAT_NANOSECONDS);
        }

        return clone self::$intervals[$nanos];
    }

    public static function timestamp(\DateTimeInterface $time): Timestamp
    {
        $timestamp = new Timestamp();
        $timestamp->fromDateTime($time);

        return $timestamp;
    }

    public static function dateTime(?Timestamp $timestamp, \DateTimeZone $zone): \DateTimeImmutable
    {
        $time = $timestamp === null ? new \DateTimeImmutable() : \DateTimeImmutable::createFromInterface($timestamp->toDateTime());

        return $time->setTimezone($zone);
    }

    public static function micros(?Timestamp $timestamp): int
    {
        return $timestamp === null
            ? (int) (new \DateTimeImmutable())->format('Uu')
            : $timestamp->getSeconds() * self::MICROS_PER_SECOND + \intdiv($timestamp->getNanos(), self::NANOS_PER_MICRO);
    }

    public static function microsOf(?Duration $duration): int
    {
        return $duration === null ? 0 : $duration->getSeconds() * self::MICROS_PER_SECOND + \intdiv($duration->getNanos(), self::NANOS_PER_MICRO);
    }

    public static function utcMilliseconds(int $micros): Carbon
    {
        return new Carbon(
            \gmdate('Y-m-d\TH:i:s', \intdiv($micros, self::MICROS_PER_SECOND))
            . \sprintf('.%03d+00:00', \intdiv($micros % self::MICROS_PER_SECOND, self::NANOS_PER_MICRO)),
        );
    }
}
