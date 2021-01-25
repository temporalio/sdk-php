<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

/**
 * @psalm-type TypeHint = string | \ReflectionClass | \ReflectionType | Type
 */
final class Type
{
    public const STRING = 'string';
    public const BOOL = 'bool';
    public const INT = 'int';
    public const FLOAT = 'float';

    /**
     * @var string|null
     */
    private ?string $name;

    /**
     * @var bool
     */
    private bool $allowsNull;

    /**
     * @param string|null $name
     * @param bool $allowsNull
     */
    public function __construct(string $name = null, bool $allowsNull = false)
    {
        $this->name = $name;
        $this->allowsNull = $allowsNull;
    }

    /**
     * @return string
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
     * @param \ReflectionClass $r
     * @param bool $allowsNull
     * @return Type
     */
    public static function fromReflectionClass(\ReflectionClass $r, bool $allowsNull = false)
    {
        return new self($r->getName(), $allowsNull);
    }

    /**
     * @param \ReflectionType $r
     * @return Type
     */
    public static function fromReflectionType(\ReflectionType $r)
    {
        if ($r instanceof \ReflectionNamedType) {
            return new self($r->getName(), $r->allowsNull());
        }

        return new self(null, $r->allowsNull());
    }

    /**
     * @param TypeHint $type
     * @return Type
     */
    public static function fromMixed($type): Type
    {
        switch (true) {
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
