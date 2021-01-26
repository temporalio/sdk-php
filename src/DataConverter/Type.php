<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use Temporal\Workflow\ReturnType;

/**
 * @psalm-type TypeEnum = Type::TYPE_*
 */
final class Type
{
    public const TYPE_ANY       = null;
    public const TYPE_STRING    = 'string';
    public const TYPE_BOOL      = 'bool';
    public const TYPE_INT       = 'int';
    public const TYPE_FLOAT     = 'float';

    /**
     * @var string|null
     */
    private ?string $name;

    /**
     * @var bool
     */
    private bool $allowsNull;

    /**
     * @param TypeEnum|string|null $name
     * @param bool $allowsNull
     */
    public function __construct(string $name = self::TYPE_ANY, bool $allowsNull = false)
    {
        $this->name = $name;
        $this->allowsNull = $allowsNull;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
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
        return $this->name === null;
    }

    /**
     * @return bool
     */
    public function isClass(): bool
    {
        return !$this->isUntyped() && class_exists($this->name);
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

            // Traversable types (i.e. Generator) not allowed
            if (! $name instanceof \Traversable && $name !== 'array') {
                return new self($type->getName(), $type->allowsNull());
            }
        }

        return new self(null, $type->allowsNull());
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
