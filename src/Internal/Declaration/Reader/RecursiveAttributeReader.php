<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Spiral\Attributes\ReaderInterface;

/**
 * @psalm-template T of object
 */
class RecursiveAttributeReader
{
    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var array<positive-int, \ReflectionClass>
     */
    private array $inheritance = [];

    /**
     * @var array<string, array<object>>
     */
    private array $attributes = [];

    /**
     * @var string
     */
    private string $class;

    /**
     * @param ReaderInterface $reader
     * @param \ReflectionClass $class
     * @param string $attribute
     */
    public function __construct(ReaderInterface $reader, \ReflectionClass $class, string $attribute)
    {
        $this->reader = $reader;
        $this->class = $class->getName();

        $this->inheritance = \array_reverse(
            \iterator_to_array($this->getClassInheritance($class), false)
        );

        foreach ($this->inheritance as $ctx) {
            $this->attributes[$ctx->getName()] = $this->reader->firstClassMetadata($ctx, $attribute);
        }
    }

    /**
     * @param \ReflectionMethod $fun
     * @param string $attribute
     * @return \Traversable
     * @throws \ReflectionException
     */
    public function bypass(\ReflectionMethod $fun, string $attribute): \Traversable
    {
        return $this->bypassThrough($fun, $attribute, static fn (?object $attr): ?object => $attr);
    }

    /**
     * @param \ReflectionMethod $fun
     * @param string $attribute
     * @param \Closure $reducer
     * @return \Traversable
     * @throws \ReflectionException
     */
    public function bypassThrough(\ReflectionMethod $fun, string $attribute, \Closure $reducer): \Traversable
    {
        foreach ($this->getMethodInheritance($fun) as $class => $method) {
            $name = $class->getName();

            yield $reducer(
                $this->reader->firstFunctionMetadata($method, $attribute),
                $method,
                $this->attributes[$name] ?? null,
                $name === $this->class
            );
        }
    }

    /**
     * @param \ReflectionMethod $method
     * @return \Traversable<\ReflectionClass, \ReflectionMethod>
     * @throws \ReflectionException
     */
    protected function getMethodInheritance(\ReflectionMethod $method): \Traversable
    {
        foreach ($this->inheritance as $context) {
            if ($context->hasMethod($method->getName())) {
                $reflection = $context->getMethod($method->getName());

                if (! $reflection->isPublic()) {
                    continue;
                }

                yield $context => $reflection;
            }
        }
    }

    /**
     * @param \ReflectionClass $class
     * @return \Traversable<positive-int, \ReflectionClass>
     */
    private function getClassInheritance(\ReflectionClass $class): \Traversable
    {
        yield $class;

        foreach ($class->getTraits() ?? [] as $trait) {
            yield from $this->getClassInheritance($trait);
        }

        foreach ($class->getInterfaces() as $interface) {
            if (! $this->isDirectInterfaceImplementation($class, $interface)) {
                continue;
            }

            yield from $this->getClassInheritance($interface);
        }

        if ($parent = $class->getParentClass()) {
            yield from $this->getClassInheritance($parent);
        }
    }

    /**
     * @param \ReflectionClass $class
     * @param \ReflectionClass $interface
     * @return bool
     */
    private function isDirectInterfaceImplementation(\ReflectionClass $class, \ReflectionClass $interface): bool
    {
        if ($parent = $class->getParentClass()) {
            return ! $parent->implementsInterface($interface);
        }

        return true;
    }
}
