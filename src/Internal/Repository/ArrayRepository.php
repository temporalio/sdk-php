<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Repository;

/**
 * @template-covariant TEntry of Identifiable
 *
 * @psalm-import-type Identifier from Identifiable
 * @template-implements RepositoryInterface<TEntry>
 */
class ArrayRepository implements RepositoryInterface
{
    /**
     * @var string
     */
    private const ERROR_ALREADY_EXISTS = 'Entry with same identifier "%s" already has been registered';

    /**
     * @var array<Identifier, Identifiable>
     */
    private array $entries = [];

    /**
     * @param iterable<Identifiable> $entries
     */
    public function __construct(iterable $entries = [], bool $overwrite = false)
    {
        foreach ($entries as $entry) {
            $this->add($entry, $overwrite);
        }
    }

    public function filter(callable $filter): RepositoryInterface
    {
        return new static(\array_filter($this->entries, $filter));
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->entries);
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    public function add(Identifiable $entry, bool $overwrite = false): void
    {
        $name = $entry->getID();

        if ($overwrite === false && isset($this->entries[$name])) {
            throw new \OutOfBoundsException(\sprintf(self::ERROR_ALREADY_EXISTS, $name));
        }

        $this->entries[$name] = $entry;
    }

    public function find($id): ?Identifiable
    {
        return $this->entries[$id] ?? null;
    }

    public function has($id): bool
    {
        return isset($this->entries[$id]);
    }

    public function remove($id): void
    {
        unset($this->entries[$id]);
    }
}
