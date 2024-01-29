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
 * The task of the {@see RepositoryInterface} is to be able to register a
 * new {@see object} and return it by its identifier.
 *
 * @template-covariant TEntry of Identifiable
 *
 * @psalm-import-type Identifier from Identifiable
 * @implements \IteratorAggregate<Identifier, TEntry>
 */
interface RepositoryInterface extends \IteratorAggregate, \Countable
{
    /**
     * @param callable(Identifiable): bool $filter
     * @return $this
     */
    public function filter(callable $filter): self;

    /**
     * Register a new {@see Identifiable} inside the repository.
     *
     * @param Identifiable $entry
     * @param bool $overwrite
     */
    public function add(Identifiable $entry, bool $overwrite = false): void;

    /**
     * Returns a {@see Identifiable} by its task queue identifier
     * or {@see null} in the case that such worker with passed task queue
     * identifier argument was not found.
     *
     * @param Identifier $id
     * @return TEntry|null
     */
    public function find($id): ?Identifiable;

    /**
     * Returns {@see true} when entry with given ID is present or {@see false}
     * otherwise.
     *
     * @param Identifier $id
     * @return bool
     */
    public function has($id): bool;

    /**
     * Removes entry with given id from repository.
     *
     * @param Identifier $id
     */
    public function remove($id): void;
}
