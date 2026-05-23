<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\MarshallingRule;

/**
 * @extends Type<array, array>
 */
class ArrayType extends Type implements DetectableTypeInterface, RuleFactoryInterface
{
    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE = 'Passed value must be a type of array, but %s given';

    private ?TypeInterface $type = null;

    /**
     * @throws \ReflectionException
     */
    public function __construct(MarshallerInterface $marshaller, MarshallingRule|string|null $typeOrClass = null)
    {
        if ($typeOrClass !== null) {
            $this->type = $this->ofType($marshaller, $typeOrClass);
        }

        parent::__construct($marshaller);
    }

    public static function match(\ReflectionNamedType $type): bool
    {
        return $type->getName() === 'array' || $type->getName() === 'iterable';
    }

    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || !\in_array($type->getName(), ['array', 'iterable'], true)) {
            return null;
        }

        return $type->allowsNull()
            ? new MarshallingRule($property->getName(), NullableType::class, self::class)
            : new MarshallingRule($property->getName(), self::class);
    }

    /**
     * @param mixed $value
     * @param array $current
     */
    public function parse($value, $current): array
    {
        if ($value === null) {
            return [];
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, \get_debug_type($value)));
        }

        if ($this->type) {
            $result = [];

            foreach ($value as $i => $item) {
                $result[$i] = $this->type->parse($item, $current[$i] ?? null);
            }

            return $result;
        }

        return $value;
    }

    /**
     * @param iterable $value
     */
    public function serialize($value): array
    {
        if ($this->type) {
            $result = [];

            foreach ($value as $i => $item) {
                $result[$i] = $this->type->serialize($item);
            }

            return $result;
        }

        if (\is_array($value)) {
            return $value;
        }

        // Convert iterable to array
        $result = [];
        foreach ($value as $i => $item) {
            $result[$i] = $item;
        }
        return $result;
    }
}
