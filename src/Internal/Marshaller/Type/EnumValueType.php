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
 * @extends Type<int|string, \BackedEnum>
 */
class EnumValueType extends Type implements RuleFactoryInterface
{
    private const ERROR_MESSAGE = 'Invalid Enum value. Expected: int or string scalar value for BackedEnum. %s given.';

    /** @var class-string<\BackedEnum> */
    private string $classFQCN;

    /**
     * @param class-string<\BackedEnum>|null $class
     */
    public function __construct(MarshallerInterface $marshaller, ?string $class = null)
    {
        $this->classFQCN = $class ?? throw new \RuntimeException('Enum is required.');
        \is_a($class, \BackedEnum::class, true) ?: throw new \RuntimeException(
            'Class for EnumValueType must be an instance of BackedEnum.',
        );
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
        if ($value instanceof \BackedEnum) {
            return $value;
        }

        if (\is_int($value) || \is_string($value)) {
            return $this->classFQCN::from($value);
        }

        throw new \InvalidArgumentException(\sprintf(self::ERROR_MESSAGE, \ucfirst(\get_debug_type($value))));
    }

    public function serialize($value): int|string
    {
        return $value->value;
    }
}
