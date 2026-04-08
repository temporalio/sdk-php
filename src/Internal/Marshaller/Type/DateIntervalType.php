<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Carbon\CarbonInterval;
use Google\Protobuf\Duration;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Inheritance;

/**
 * @psalm-import-type DateIntervalFormat from DateInterval
 * @psalm-import-type DateIntervalValue from DateInterval
 * @extends Type<int|Duration, DateIntervalValue>
 */
class DateIntervalType extends Type implements DetectableTypeInterface, RuleFactoryInterface
{
    private string $format;

    public function __construct(MarshallerInterface $marshaller, string $format = DateInterval::FORMAT_NANOSECONDS)
    {
        $this->format = $format;

        parent::__construct($marshaller);
    }

    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() && Inheritance::extends($type->getName(), \DateInterval::class);
    }

    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || !\is_a($type->getName(), \DateInterval::class, true)) {
            return null;
        }

        return $type->allowsNull()
            ? new MarshallingRule($property->getName(), NullableType::class, self::class)
            : new MarshallingRule($property->getName(), self::class);
    }

    public function serialize($value): int|Duration
    {
        if ($this->format === Duration::class) {
            return match (true) {
                $value instanceof \DateInterval => DateInterval::toDuration($value),
                \is_int($value) => (new Duration())->setSeconds($value),
                \is_string($value) => (new Duration())->setSeconds((int) $value),
                \is_float($value) => (new Duration())
                    ->setSeconds((int) $value)
                    ->setNanos(($value * 1000000000) % 1000000000),
                default => throw new \InvalidArgumentException('Invalid value type.'),
            };
        }

        $interval = DateInterval::parse($value, $this->format);

        return (int) match ($this->format) {
            DateInterval::FORMAT_YEARS => $interval->totalYears,
            DateInterval::FORMAT_MONTHS => $interval->totalMonths,
            DateInterval::FORMAT_WEEKS => $interval->totalWeeks,
            DateInterval::FORMAT_DAYS => $interval->totalDays,
            DateInterval::FORMAT_HOURS => $interval->totalHours,
            DateInterval::FORMAT_MINUTES => $interval->totalMinutes,
            DateInterval::FORMAT_SECONDS => $interval->totalSeconds,
            DateInterval::FORMAT_MILLISECONDS => $interval->totalMilliseconds,
            DateInterval::FORMAT_MICROSECONDS => $interval->totalMicroseconds,
            DateInterval::FORMAT_NANOSECONDS => (int) \round($interval->totalMicroseconds * 1000),
            default => throw new \InvalidArgumentException(
                \sprintf(
                    'Unsupported format: "%s". See %s for available formats.',
                    $this->format,
                    DateInterval::class,
                ),
            ),
        };
    }

    public function parse($value, $current): CarbonInterval
    {
        return DateInterval::parse($value, $this->format);
    }
}
