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
 * @template TClass of object
 * @extends Type<array, mixed>
 */
class ObjectType extends Type implements DetectableTypeInterface, RuleFactoryInterface
{
    /**
     * @var \ReflectionClass<TClass>
     */
    private \ReflectionClass $reflection;

    /**
     * @param class-string<TClass>|null $class
     * @throws \ReflectionException
     */
    public function __construct(MarshallerInterface $marshaller, ?string $class = null)
    {
        $this->reflection = new \ReflectionClass($class ?? \stdClass::class);

        parent::__construct($marshaller);
    }

    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() || $type->getName() === 'object';
    }

    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
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

    public function parse($value, $current): object
    {
        if (\is_object($value) && $this->reflection->isInstance($value)) {
            return $value;
        }

        if ($current === null) {
            $current = $this->emptyInstance();
        }

        if ($current instanceof \stdClass && $this->reflection->getName() === \stdClass::class) {
            foreach ($value as $key => $val) {
                $current->$key = $val;
            }

            return $current;
        }

        return $this->marshaller->unmarshal($value ?? [], $current);
    }

    public function serialize($value): array
    {
        return $this->reflection->getName() === \stdClass::class
            ? (array) $value
            : $this->marshaller->marshal($value);
    }

    /**
     * @return TClass
     *
     * @throws \ReflectionException
     */
    protected function emptyInstance(): object
    {
        return $this->reflection->newInstanceWithoutConstructor();
    }

    /**
     * @return TClass
     * @throws \ReflectionException
     *
     * @deprecated This method is not used anymore and will be removed in the next major release.
     */
    protected function instance(array $data): object
    {
        return $this->reflection->getName() === \stdClass::class
            ? (object) $data
            : $this->marshaller->unmarshal($data, $this->reflection->newInstanceWithoutConstructor());
    }
}
