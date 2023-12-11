<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

final class DateTime
{
    /**
     * @var string
     */
    private const NOTICE_PRECISION_LOSS =
        'When reading the RFC3339 datetime, a conversion was made from the ' .
        '%s format to the %s format with a loss of precision (round to microseconds).';

    /**
     * @template TReturn of \DateTimeInterface
     *
     * @param string|\DateTimeInterface|null $time
     * @param \DateTimeZone|string|null $tz
     * @param class-string<TReturn> $class
     *
     * @return TReturn
     */
    public static function parse($time = null, $tz = null, string $class = \DateTimeInterface::class): \DateTimeInterface
    {
        if (\is_string($time) && $matched = self::extractRfc3339Accuracy($time)) {
            [$datetime, $accuracy] = $matched;

            // Note: PHP does not support accuracy greater than 8 (microseconds)
            if (\strlen($accuracy) > 8) {
                $time = \sprintf('%s.%sZ', $datetime, \substr($accuracy, 0, 8));
            }
        }

        if ($time instanceof $class) {
            return $time;
        }

        return match ($class) {
            \DateTimeImmutable::class => new \DateTimeImmutable($time, $tz),
            \DateTime::class => new \DateTime($time, $tz),
            CarbonImmutable::class => CarbonImmutable::parse($time, $tz),
            default => Carbon::parse($time, $tz),
        };
    }

    /**
     * Split date in RFC3339 format to "date" and "accuracy" array or
     * return {@see null} in the case that the passed time string is not valid
     * RFC3339 or ISO8601 string.
     *
     * TODO: This match function can only parse the "Z" timezone, and in the
     *       case of an explicit timezone "+00:00" this case will be ignored.
     *
     * @param string $time
     * @return null|array{0: string, 1: string}
     */
    private static function extractRfc3339Accuracy(string $time): ?array
    {
        $likeRfc3339WithAccuracy = \str_ends_with($time, 'Z')
            && \substr_count($time, '.') === 1
        ;

        if ($likeRfc3339WithAccuracy) {
            // $date is "YYYY-mm-dd HH:ii:ss"
            // $accuracy is "PPPP+" where P is digit of [milli/micro/nano] seconds
            [$date, $accuracy] = \explode('.', \substr($time, 0, -1));

            if (\ctype_digit($accuracy)) {
                return [$date, $accuracy];
            }
        }

        return null;
    }
}
