<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller;

use Spiral\Attributes\ReaderInterface;
use Temporal\Client\Internal\Marshaller\Meta\MarshalAs;
use Temporal\Client\Internal\Marshaller\Meta\Scope;
use Temporal\Client\Internal\Marshaller\Type\TypeInterface;

/**
 * @psalm-import-type Getter from MapperInterface
 * @psalm-import-type Setter from MapperInterface
 */
class AttributeMapper implements MapperInterface
{
    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE = 'Mapping type must implement %s';

    /**
     * @var \ReflectionClass
     */
    private \ReflectionClass $class;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var array<string, Getter>
     */
    private array $getters = [];

    /**
     * @var array<string, Setter>
     */
    private array $setters = [];

    /**
     * @var Scope
     */
    private Scope $scope;

    /**
     * @param \ReflectionClass $class
     * @param ReaderInterface $reader
     */
    public function __construct(\ReflectionClass $class, ReaderInterface $reader)
    {
        $this->class = $class;
        $this->reader = $reader;

        $this->scope = $this->getScope();

        foreach ($this->getPropertyMappings($this->scope) as $property => $marshal) {
            $this->getters[$marshal->name] = $this->createGetter($property, $marshal);
            $this->setters[$marshal->name] = $this->createSetter($property, $marshal);
        }
    }

    /**
     * @param class-string<TypeInterface> $type
     * @param array $args
     * @return TypeInterface
     */
    private function type(string $type, array $args = []): TypeInterface
    {
        if (! \is_subclass_of($type, TypeInterface::class)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, TypeInterface::class));
        }

        return new $type(...$args);
    }

    /**
     * @param string $name
     * @param MarshalAs $meta
     * @return \Closure
     */
    private function createGetter(string $name, MarshalAs $meta): \Closure
    {
        if ($meta->type === null) {
            return static function (object $context) use ($name) {
                return (fn() => $this->$name)->call($context);
            };
        }

        $type = $this->type($meta->type, $meta->options);

        return static function (object $context) use ($name, $type) {
            return (fn() => $type->serialize($this->$name))->call($context);
        };
    }

    /**
     * @param string $name
     * @param MarshalAs $meta
     * @return \Closure
     */
    private function createSetter(string $name, MarshalAs $meta): \Closure
    {
        if ($meta->type === null) {
            return static function (object $context, $value) use ($name) {
                return (fn() => $this->$name = $value)->call($context);
            };
        }

        $type = $this->type($meta->type, $meta->options);

        return static function (object $context, $value) use ($name, $type) {
            (fn() => $this->$name = $type->parse($value))->call($context);
        };
    }

    /**
     * @return bool
     */
    public function isCopyOnWrite(): bool
    {
        return $this->scope->copyOnWrite;
    }

    /**
     * {@inheritDoc}
     */
    public function getGetters(): iterable
    {
        return $this->getters;
    }

    /**
     * {@inheritDoc}
     */
    public function getSetters(): iterable
    {
        return $this->setters;
    }

    /**
     * @return iterable
     */
    private function getPropertyMappings(Scope $scope): iterable
    {
        foreach ($this->class->getProperties() as $property) {
            /** @var MarshalAs $marshal */
            $marshal = $this->reader->firstPropertyMetadata($property, MarshalAs::class);

            $name = $property->getName();

            // Has marshal attribute
            if ($marshal === null && ! $this->isValidScope($property, $scope)) {
                continue;
            }

            $marshal ??= new MarshalAs();
            $marshal->name ??= $name;

            yield $name => $marshal;
        }
    }

    /**
     * @param \ReflectionProperty $property
     * @param Scope $scope
     * @return bool
     */
    private function isValidScope(\ReflectionProperty $property, Scope $scope): bool
    {
        return ($property->getModifiers() & $scope->properties) === $scope->properties;
    }

    /**
     * @return Scope
     */
    private function getScope(): Scope
    {
        return $this->reader->firstClassMetadata($this->class, Scope::class) ?? new Scope();
    }

    /**
     * @param string $class
     * @param ReaderInterface $reader
     * @return static
     * @throws \ReflectionException
     */
    public static function fromClass(string $class, ReaderInterface $reader): self
    {
        return new self(new \ReflectionClass($class), $reader);
    }
}
