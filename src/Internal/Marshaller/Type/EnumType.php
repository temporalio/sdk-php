<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use BackedEnum;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\MarshallingRule;

/**
 * @extends Type<array>
 */
class EnumType extends Type implements RuleFactoryInterface
{
    private const ERROR_INVALID_TYPE = 'Invalid Enum value. Expected: int or string scalar value for BackedEnum; '
        . 'array with `name` or `value` keys; a case of the Enum. %s given.';

    /** @var class-string<\UnitEnum> */
    private string $classFQCN;

    public function __construct(MarshallerInterface $marshaller, string $class = null)
    {
        if (PHP_VERSION_ID < 80104) {
            throw new \RuntimeException('Enums are not available in this version of PHP');
        }

        if ($class === null) {
            throw new \RuntimeException('Enum is required');
        }

        $this->classFQCN = $class;
        parent::__construct($marshaller);
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current)
    {
        if (\is_object($value)) {
            return $value;
        }

        if (\is_scalar($value)) {
            return $this->classFQCN::from($value);
        }

        if (\is_array($value)) {
           // Process the `value` key
            if (\array_key_exists('value', $value)) {
                return $this->classFQCN::from($value['value']);
            }

            // Process the `name` key
            if (\array_key_exists('name', $value)) {
                return (new \ReflectionClass($this->classFQCN))
                    ->getConstant($value['name']);
            }
        }

        throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, \ucfirst(\get_debug_type($value))));
    }

    /**
     * @psalm-suppress UndefinedDocblockClass
     */
    public function serialize($value): array
    {
        return $value instanceof BackedEnum
            ? [
                'name' => $value->name,
                'value' => $value->value,
            ]
            : [
                'name' => $value->name,
            ];
    }
}
