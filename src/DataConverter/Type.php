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

    /**
     * @var string
     */
    private string $name;

    /**
     * @var bool
     */
    private bool $allowsNull;

    /**
     * @param TypeEnum|string $name
     * @param bool|null $allowsNull
     */
    public function __construct(string $name = Type::TYPE_ANY, bool $allowsNull = null)
    {
        $this->name = $name;

        $this->allowsNull = $allowsNull ?? (
            $name === self::TYPE_ANY || $name === self::TYPE_VOID
        );
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
            if (PHP_VERSION_ID >= 80104 && is_subclass_of($name, \UnitEnum::class)) {
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
     * @param string|\ReflectionClass|\ReflectionType|Type $type
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
}
