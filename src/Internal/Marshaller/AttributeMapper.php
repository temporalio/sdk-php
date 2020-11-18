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
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Meta\Scope;
use Temporal\Client\Internal\Marshaller\Type\Factory;
use Temporal\Client\Internal\Marshaller\Type\TypeInterface;

/**
 * @psalm-import-type Getter from MapperInterface
 * @psalm-import-type Setter from MapperInterface
 */
class AttributeMapper implements MapperInterface
{
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
     * @var Factory
     */
    private Factory $factory;

    /**
     * @param \ReflectionClass $class
     * @param Factory $factory
     * @param MarshallerInterface $marshaller
     * @param ReaderInterface $reader
     */
    public function __construct(\ReflectionClass $class, Factory $factory, ReaderInterface $reader)
    {
        $this->class = $class;
        $this->reader = $reader;
        $this->factory = $factory;
        $this->scope = $this->getScope();

        foreach ($this->getPropertyMappings($this->scope) as $property => $marshal) {
            $type = $this->detectType($property, $marshal);
            $name = $property->getName();

            $this->getters[$marshal->name] = $this->createGetter($name, $type);
            $this->setters[$marshal->name] = $this->createSetter($name, $type);
        }
    }

    /**
     * @return Scope
     */
    private function getScope(): Scope
    {
        return $this->reader->firstClassMetadata($this->class, Scope::class) ?? new Scope();
    }

    /**
     * @return iterable<\ReflectionProperty, Marshal>
     */
    private function getPropertyMappings(Scope $scope): iterable
    {
        foreach ($this->class->getProperties() as $property) {
            /** @var Marshal $marshal */
            $marshal = $this->reader->firstPropertyMetadata($property, Marshal::class);
            $name = $property->getName();

            // Has marshal attribute
            if ($marshal === null && ! $this->isValidScope($property, $scope)) {
                continue;
            }

            $marshal ??= new Marshal();
            $marshal->name ??= $name;

            yield $property => $marshal;
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
     * @param \ReflectionProperty $property
     * @param Marshal $meta
     * @return TypeInterface|null
     */
    private function detectType(\ReflectionProperty $property, Marshal $meta): ?TypeInterface
    {
        $type = $meta->type ?? $this->factory->detect($property->getType());

        if ($type === null) {
            return null;
        }

        return $this->factory->create($type, $meta->of ? [$meta->of] : []);
    }

    /**
     * @param string $name
     * @param TypeInterface|null $type
     * @return \Closure
     */
    private function createGetter(string $name, ?TypeInterface $type): \Closure
    {
        return function () use ($name, $type) {
            try {
                $result = $this->$name;
            } catch (\Error $e) {
                return null;
            }

            return $type ? $type->serialize($result) : $result;
        };
    }

    /**
     * @param string $name
     * @param TypeInterface|null $type
     * @return \Closure
     */
    private function createSetter(string $name, ?TypeInterface $type): \Closure
    {
        return function ($value) use ($name, $type) {
            try {
                $source = $this->$name;
            } catch (\Error $e) {
                $source = null;
            }

            $this->$name = $type ? $type->parse($value, $source) : $value;
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
}
