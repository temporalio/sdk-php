<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use ReflectionNamedType;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Support\Inheritance;

/**
 * Read only type.
 */
final class EncodedCollectionType extends Type implements DetectableTypeInterface, RuleFactoryInterface
{
    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() &&
            Inheritance::extends($type->getName(), EncodedCollection::class);
    }

    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType || !self::match($type)) {
            return null;
        }

        return new MarshallingRule($property->getName(), self::class, $type->getName());
    }

    /**
     * @psalm-assert string $value
     */
    public function parse(mixed $value, mixed $current): EncodedCollection
    {
        return match (true) {
            $value === null => EncodedCollection::empty(),
            \is_array($value) => EncodedCollection::fromValues($value),
            $value instanceof EncodedCollection => $value,
            default => throw new \InvalidArgumentException('Unsupported value type'),
        };
    }

    public function serialize(mixed $value): string
    {
        throw new \BadMethodCallException('EncodedCollectionType is not serializable');
    }
}
