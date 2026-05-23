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
 * @template TClass of \UnitEnum
 * @extends Type<array, TClass>
 */
class EnumType extends Type implements RuleFactoryInterface
{
    private const ERROR_INVALID_TYPE = 'Invalid Enum value. Expected: int or string scalar value for BackedEnum; '
        . 'array with `name` or `value` keys; a case of the Enum. %s given.';

    /** @var class-string<TClass> */
    private string $classFQCN;

    /**
     * @param class-string<TClass>|null $class
     */
    public function __construct(MarshallerInterface $marshaller, ?string $class = null)
    {
        if ($class === null) {
            throw new \RuntimeException('Enum is required');
        }

        $this->classFQCN = $class;
        parent::__construct($marshaller);
    }

    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || !\is_subclass_of($type->getName(), \UnitEnum::class)) {
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

    public function parse($value, $current)
    {
        if ($value instanceof $this->classFQCN) {
            return $value;
        }

        $class = $this->classFQCN;

        if (\is_int($value) || \is_string($value)) {
            // check if classFQN is backed enum class
            if (!\is_a($class, \BackedEnum::class, true)) {
                throw new \InvalidArgumentException(
                    'Unsupported enum value type. ',
                );
            }
            /** @var TClass */
            return $class::from($value);
        }

        if (\is_array($value)) {
            // Process the `value` key
            if (\array_key_exists('value', $value) && (\is_int($value['value']) || \is_string($value['value']))) {
                // check if classFQN is backed enum class
                if (!\is_a($class, \BackedEnum::class, true)) {
                    throw new \InvalidArgumentException(
                        'Unsupported enum value type. ',
                    );
                }
                /** @var TClass */
                return $class::from($value['value']);
            }

            // Process the `name` key
            if (\array_key_exists('name', $value) && \is_string($value['name'])) {
                return (new \ReflectionClass($this->classFQCN))
                    ->getConstant($value['name']);
            }
        }

        throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, \ucfirst(\get_debug_type($value))));
    }

    public function serialize($value): array
    {
        return $value instanceof \BackedEnum
            ? [
                'name' => $value->name,
                'value' => $value->value,
            ]
            : [
                'name' => $value->name,
            ];
    }
}
