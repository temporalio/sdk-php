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

/**
 * @internal
 */
final class ProtoTime
{
    public const NANOS_PER_MILLISECOND = 1_000_000;
    private const NANOS_PER_SECOND = 1_000_000_000;
    private const NANOS_PER_MICRO = 1_000;
    private const MICROS_PER_SECOND = 1_000_000;
    private const INTERVAL_CACHE_SIZE = 64;

    /** @var array<int, CarbonInterval> */
    private static array $intervals = [];

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
            /** @psalm-suppress TypeDoesNotContainType, TypeDoesNotContainNull, RedundantCondition, PossiblyNullArrayOffset */
            self::$intervals[$nanos] = DateInterval::parse($nanos, DateInterval::FORMAT_NANOSECONDS);
        }

        return clone self::$intervals[$nanos];
    }

    public static function timestamp(\DateTimeInterface $time): Timestamp
    {
        $timestamp = new Timestamp();
        $timestamp->fromDateTime(\DateTime::createFromInterface($time));

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
            : self::microsOf($timestamp);
    }

    public static function microsOf(Timestamp|Duration|null $value): int
    {
        return $value === null ? 0 : (int) $value->getSeconds() * self::MICROS_PER_SECOND + \intdiv($value->getNanos(), self::NANOS_PER_MICRO);
    }

    public static function utc(int $micros): Carbon
    {
        return new Carbon(
            \gmdate('Y-m-d\TH:i:s', \intdiv($micros, self::MICROS_PER_SECOND))
            . \sprintf('.%06d+00:00', $micros % self::MICROS_PER_SECOND),
        );
    }

    private static function nanos(?Duration $duration): int
    {
        return $duration === null ? 0 : (int) $duration->getSeconds() * self::NANOS_PER_SECOND + $duration->getNanos();
    }
}
