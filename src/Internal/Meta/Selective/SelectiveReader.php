<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Meta\Selective;

use Temporal\Client\Internal\Meta\ReaderInterface;

class SelectiveReader implements ReaderInterface
{
    /**
     * @var ReaderInterface[]
     */
    private array $readers;

    /**
     * @param ReaderInterface[] $readers
     */
    public function __construct(array $readers)
    {
        $this->readers = $readers;
    }

    /**
     * {@inheritDoc}
     */
    public function getClassMetadata(\ReflectionClass $class, string $name = null): iterable
    {
        return $this->resolve(fn(ReaderInterface $reader): iterable => $reader->getClassMetadata($class, $name));
    }

    /**
     * @psalm-param callable(ReaderInterface): list<array-key, object> $resolver
     *
     * @param callable $resolver
     * @return iterable
     */
    private function resolve(callable $resolver): iterable
    {
        foreach ($this->readers as $reader) {
            $result = $this->iterableToArray($resolver($reader));

            if (\count($result) > 0) {
                return $result;
            }
        }

        return [];
    }

    /**
     * @param \Traversable|array $result
     * @return array
     */
    private function iterableToArray(iterable $result): array
    {
        return $result instanceof \Traversable ? \iterator_to_array($result, false) : $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethodMetadata(\ReflectionMethod $method, string $name = null): iterable
    {
        return $this->resolve(fn(ReaderInterface $reader): iterable => $reader->getMethodMetadata($method, $name));
    }

    /**
     * {@inheritDoc}
     */
    public function getPropertyMetadata(\ReflectionProperty $property, string $name = null): iterable
    {
        return $this->resolve(fn(ReaderInterface $reader): iterable => $reader->getPropertyMetadata($property, $name));
    }
}
