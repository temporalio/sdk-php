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
     * @param \ReflectionClass $class
     * @param ReaderInterface $reader
     */
    public function __construct(\ReflectionClass $class, ReaderInterface $reader)
    {
        $this->class = $class;
        $this->reader = $reader;

        $this->scope = $this->getScope();

        foreach ($this->getPropertyMappings($this->scope) as $property => $out) {
            $this->getters[$out] = function (object $context) use ($property) {
                return (fn() => $this->$property)->call($context);
            };

            $this->setters[$out] = function (object $context, $value) use ($property) {
                (fn() => $this->$property = $value)->call($context);
            };
        }
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
            if ($marshal !== null || $this->isValidScope($property, $scope)) {
                yield $name => $marshal->name ?? $name;
            }
        }
    }

    /**
     * TODO Optimise it
     *
     * @param \ReflectionProperty $property
     * @param Scope $scope
     * @return bool
     */
    private function isValidScope(\ReflectionProperty $property, Scope $scope): bool
    {
        $current = $scope->properties;

        return ($property->isPublic() && ($current & Scope::PROPERTY_PUBLIC) === Scope::PROPERTY_PUBLIC)
            || ($property->isProtected() && ($current & Scope::PROPERTY_PROTECTED) === Scope::PROPERTY_PROTECTED)
            || ($property->isPrivate() && ($current & Scope::PROPERTY_PRIVATE) === Scope::PROPERTY_PRIVATE)
        ;
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

    /**
     * @param object $object
     * @param ReaderInterface $reader
     * @return static
     */
    public static function fromObject(object $object, ReaderInterface $reader): self
    {
        return new self(new \ReflectionObject($object), $reader);
    }
}
