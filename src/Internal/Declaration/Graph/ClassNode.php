<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Graph;

use JetBrains\PhpStorm\Pure;
use Spiral\Attributes\ReaderInterface;

final class ClassNode implements NodeInterface
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
     * @var array|null
     */
    private ?array $inheritance = null;

    /**
     * @param \ReflectionClass $class
     * @param ReaderInterface $reader
     */
    public function __construct(\ReflectionClass $class, ReaderInterface $reader)
    {
        $this->class = $class;
        $this->reader = $reader;
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count(
            $this->inheritance ??= $this->getClassInheritance()
        );
    }

    /**
     * @return array
     */
    private function getClassInheritance(): array
    {
        $result = [];
        $result[] = [$this];

        foreach ($this->getSiblingInheritance() as $siblings) {
            $result[] = $siblings;
        }

        if ($parent = $this->getParent()) {
            foreach ($parent->getClassInheritance() as $group) {
                $result[] = $group;
            }
        }

        return $result;
    }

    /**
     * @return \Traversable<ClassNode>
     */
    private function getSiblingInheritance(): \Traversable
    {
        $siblings = \iterator_to_array($this->getSiblings(), false);

        if (\count($siblings) > 0) {
            yield $siblings;

            foreach ($siblings as $sibling) {
                yield from $sibling->getSiblingInheritance();
            }
        }
    }

    /**
     * @return \Traversable<ClassNode>
     */
    private function getSiblings(): \Traversable
    {
        // Traits
        foreach ($this->class->getTraits() ?? [] as $trait) {
            yield new ClassNode($trait, $this->reader);
        }

        // Interfaces
        foreach ($this->class->getInterfaces() as $interface) {
            if (! $this->isDirectInterfaceImplementation($interface)) {
                continue;
            }

            yield new ClassNode($interface, $this->reader);
        }
    }

    /**
     * @param \ReflectionClass $interface
     * @return bool
     */
    private function isDirectInterfaceImplementation(\ReflectionClass $interface): bool
    {
        if ($parent = $this->class->getParentClass()) {
            return ! $parent->implementsInterface($interface);
        }

        return $this->class->implementsInterface($interface);
    }

    /**
     * @param string $name
     * @return bool
     * @throws \ReflectionException
     */
    private function isDirectMethodImplementation(string $name): bool
    {
        if ($this->class->hasMethod($name)) {
            $context = $this->class->getMethod($name)
                ->getDeclaringClass()
            ;

            return $context->getName() === $this->class->getName();
        }

        return false;
    }

    /**
     * @return ClassNode|null
     */
    #[Pure]
    private function getParent(): ?ClassNode
    {
        if ($parent = $this->class->getParentClass()) {
            return new ClassNode($parent, $this->reader);
        }

        return null;
    }

    /**
     * @param string $name
     * @return iterable<\ReflectionMethod>
     * @throws \ReflectionException
     */
    public function getMethods(string $name): iterable
    {
        foreach ($this->getInheritance() as $classes) {
            $result = $this->boxMethods($classes, $name);

            if (\count($result)) {
                yield from $this->unboxMethods($result);
            }
        }
    }

    /**
     * @return array<positive-int, array<positive-int, ClassNode>>
     */
    private function getInheritance(): array
    {
        return $this->inheritance ??= $this->getClassInheritance();
    }

    /**
     * @param iterable<ClassNode> $classes
     * @param string $name
     * @return array
     * @throws \ReflectionException
     */
    private function boxMethods(iterable $classes, string $name): array
    {
        $result = [];

        foreach ($classes as $class) {
            if ($class->isDirectMethodImplementation($name)) {
                $result[] = [$class, $class->class->getMethod($name)];
            }
        }

        return $result;
    }

    /**
     * @param array $boxed
     * @return \Traversable
     */
    private function unboxMethods(array $boxed): \Traversable
    {
        $unpack = static function () use ($boxed) {
            foreach ($boxed as [$class, $method]) {
                yield $class => $method;
            }
        };

        return $unpack();
    }

    /**
     * @return \Traversable<array<positive-int, ClassNode>>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(
            $this->inheritance ??= $this->getClassInheritance()
        );
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->class->getName();
    }
}
