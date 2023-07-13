<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Workflow\ReturnType;

/**
 * @psalm-type TypeEnum = Type::TYPE_*
 */
final class Type
{
    public const TYPE_ANY       = 'mixed';
    public const TYPE_ARRAY     = 'array';
    public const TYPE_OBJECT    = 'object';
    public const TYPE_STRING    = 'string';
    public const TYPE_BOOL      = 'bool';
    public const TYPE_INT       = 'int';
    public const TYPE_FLOAT     = 'float';
    public const TYPE_VOID      = 'void';
    public const TYPE_NULL      = 'null';
    public const TYPE_TRUE      = 'true';
    public const TYPE_FALSE     = 'false';

    /**
     * @var bool
     */
    private readonly bool $allowsNull;

    /**
     * @param TypeEnum|string $name
     * @param bool|null $allowsNull
     * @param bool $isArrayOf
     */
    public function __construct(
        private readonly string $name = Type::TYPE_ANY,
        bool $allowsNull = null,
        private readonly bool $isArrayOf = false,
    ) {
        $this->allowsNull = $allowsNull ?? (
            $name === self::TYPE_ANY || $name === self::TYPE_VOID || $name === self::TYPE_NULL
        );
    }

    public static function arrayOf(string $class): self
    {
        return new self($class, null, true);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function allowsNull(): bool
    {
        return $this->allowsNull;
    }

    /**
     * @return bool
     */
    public function isUntyped(): bool
    {
        return $this->name === self::TYPE_ANY;
    }

    /**
     * @return bool
     */
    public function isClass(): bool
    {
        return \class_exists($this->name);
    }

    /**
     * @param \ReflectionClass $class
     * @param bool $nullable
     * @return Type
     */
    public static function fromReflectionClass(\ReflectionClass $class, bool $nullable = false): self
    {
        return new self($class->getName(), $nullable);
    }

    /**
     * @param \ReflectionType $type
     * @return Type
     */
    public static function fromReflectionType(\ReflectionType $type): self
    {
        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();

            /** @psalm-suppress UndefinedClass */
            if (PHP_VERSION_ID >= 80104 && \is_subclass_of($name, \UnitEnum::class)) {
                return new self($type->getName(), true);
            }

            // Traversable types (i.e. Generator) not allowed
            if (!$name instanceof \Traversable && $name !== 'iterable') {
                return new self($type->getName(), $type->allowsNull());
            }
        }

        return new self(self::TYPE_ANY, $type->allowsNull());
    }

    /**
     * @param string|\ReflectionClass|\ReflectionType|Type|ReturnType $type
     * @return Type
     */
    public static function create($type): Type
    {
        switch (true) {
            case $type instanceof ReturnType:
                return new self($type->name, $type->nullable);

            case $type instanceof self:
                return $type;

            case \is_string($type):
                return new self($type);

            case $type instanceof \ReflectionClass:
                return self::fromReflectionClass($type);

            case $type instanceof \ReflectionType:
                return self::fromReflectionType($type);

            default:
                return new self();
        }
    }

    public function isArrayOf(): bool
    {
        return $this->isArrayOf;
    }
}
