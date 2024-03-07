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
            \is_string($value) => (new Duration())->setSeconds((int)$value),
            \is_float($value) => (new Duration())
                ->setSeconds((int)$value)
                ->setNanos(($value * 1000000000) % 1000000000),
            default => throw new \InvalidArgumentException('Invalid value type.'),
        };

        return $duration === null ? null : ['seconds' => $duration->getSeconds(), 'nanos' => $duration->getNanos()];
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current): CarbonInterval
    {
        return DateInterval::parse($value);
    }
}
