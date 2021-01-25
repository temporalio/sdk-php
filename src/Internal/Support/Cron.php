<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

use Cron\CronExpression;
use Temporal\Common\CronSchedule;

final class Cron
{
    /**
     * @param mixed $value
     * @return CronExpression|null
     */
    public static function parseOrNull($value): ?CronExpression
    {
        if ($value === null) {
            return null;
        }

        return self::parse($value);
    }

    /**
     * @param mixed $value
     * @return CronExpression
     */
    public static function parse($value): CronExpression
    {
        switch (true) {
            case $value instanceof CronExpression:
                return $value;

            case \is_string($value):
                return new CronExpression($value);

            case $value instanceof CronSchedule:
                return $value->interval;

            default:
                return self::fromDateInterval($value);
        }
    }

    /**
     * @param mixed $interval
     * @return CronExpression
     */
    private static function fromDateInterval($interval): CronExpression
    {
        $interval = DateInterval::parse($interval);

        $expression = \vsprintf('%s %s %s %s *', [
            $interval->minutes,
            $interval->hours,
            $interval->days,
            $interval->months,
        ]);

        return new CronExpression($expression);
    }
}
