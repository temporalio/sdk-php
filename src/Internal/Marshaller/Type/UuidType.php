<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionNamedType;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Support\Inheritance;

/**
 * @extends Type<string>
 */
final class UuidType extends Type implements DetectableTypeInterface, RuleFactoryInterface
{
    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() &&
            Inheritance::implements($type->getName(), UuidInterface::class);
    }

    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType || !self::match($type)) {
            return null;
        }

        return $type->allowsNull()
            ? new MarshallingRule(
                $property->getName(),
                NullableType::class,
                new MarshallingRule(type: self::class, of: $type->getName()),
            )
            : new MarshallingRule($property->getName(), self::class, $type->getName());
    }

    /**
     * @psalm-assert string $value
     */
    public function parse(mixed $value, mixed $current): UuidInterface
    {
        return Uuid::fromString($value);
    }

    /**
     * @psalm-assert UuidInterface $value
     */
    public function serialize(mixed $value): string
    {
        return $value->toString();
    }
}
