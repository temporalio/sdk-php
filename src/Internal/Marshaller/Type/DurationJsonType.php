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
 * @extends Type<int|Duration>
 */
class DurationJsonType extends Type implements DetectableTypeInterface, RuleFactoryInterface
{
    /**
     * @var DateIntervalFormat
     */
    private string $fallbackFormat;

    /**
     * @param MarshallerInterface $marshaller
     * @param DateIntervalFormat $format Fall back format for parsing when the value is not an array.
     */
    public function __construct(MarshallerInterface $marshaller, string $format = DateInterval::FORMAT_NANOSECONDS)
    {
        $this->fallbackFormat = $format;

        parent::__construct($marshaller);
    }

    /**
     * {@inheritDoc}
     */
    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() && Inheritance::extends($type->getName(), \DateInterval::class);
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function serialize($value): ?array
    {
        $duration = match (true) {
            $value instanceof \DateInterval => DateInterval::toDuration($value),
            \is_int($value) => (new Duration())->setSeconds($value),
            \is_string($value) => (new Duration())->setSeconds((int) $value),
            \is_float($value) => (new Duration())
                ->setSeconds((int) $value)
                ->setNanos(($value * 1000000000) % 1000000000),
            default => throw new \InvalidArgumentException('Invalid value type.'),
        };

        return ['seconds' => $duration->getSeconds(), 'nanos' => $duration->getNanos()];
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current): CarbonInterval
    {
        if (\is_array($value) && isset($value['seconds']) && isset($value['nanos'])) {
            // The highest precision is milliseconds either way.
            $value = $value['seconds'] * 1_000_000_000 + $value['nanos'];
            return DateInterval::parse($value, DateInterval::FORMAT_NANOSECONDS);
        }

        return DateInterval::parse($value, $this->fallbackFormat);
    }
}
