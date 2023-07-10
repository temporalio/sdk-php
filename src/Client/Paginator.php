<?php

declare(strict_types=1);

namespace Temporal\Client;

use Countable;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * Paginator that allows to iterate over all pages.
 *
 * @template TItem
 * @implements IteratorAggregate<TItem>
 */
final class Paginator implements IteratorAggregate, Countable
{
    /** @var list<TItem> */
    private array $collection;
    /** @var self<TItem>|null */
    private ?self $nextPage = null;
    private ?int $totalItems = null;

    /**
     * @param Generator<array-key, list<TItem>> $loader
     * @param int<1, max> $pageNumber
     */
    private function __construct(
        private Generator $loader,
        private int $pageNumber,
        private ?\Closure $counter,
    ) {
        $this->collection = $loader->current();
    }

    /**
     * @template TInitItem
     *
     * @param Generator<array-key, list<TInitItem>> $loader
     * @param null|callable(): int $counter Returns total number of items.
     *
     * @return self<TInitItem>
     */
    public static function createFromGenerator(Generator $loader, ?callable $counter): self
    {
        return new self($loader, 1, \Closure::fromCallable($counter));
    }

    /**
     * Load next page.
     *
     * @return self<TItem>|null
     */
    public function getNextPage(): ?self
    {
        if ($this->nextPage !== null) {
            return $this->nextPage;
        }

        $this->loader->next();
        if (!$this->loader->valid()) {
            return null;
        }
        $this->nextPage = new self($this->loader, $this->pageNumber + 1, $this->counter);
        $this->nextPage->counter = &$this->nextPage;

        return $this->nextPage;
    }

    /**
     * @return array<TItem>
     */
    public function getPageItems(): array
    {
        return $this->collection;
    }

    /**
     * Iterate all items from current page and all next pages.
     *
     * @return Traversable<TItem>
     */
    public function getIterator(): Traversable
    {
        $paginator = $this;
        while ($paginator !== null) {
            foreach ($paginator->getPageItems() as $item) {
                yield $item;
            }

            $paginator = $paginator->getNextPage();
        }
    }

    /**
     * @return int<1, max>
     */
    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    /**
     * Value is cached in all produced pages after first call in any page.
     *
     * Note: the method may call yet another RPC to get total number of items.
     * It means that the result may be different from the number of items at the moment of the pagination start.
     *
     * @return int<0, max>
     * @throws \LogicException If counter is not set.
     */
    public function count(): int
    {
        if ($this->totalItems !== null) {
            return $this->totalItems;
        }

        if ($this->counter === null) {
            throw new \LogicException('Paginator does not support counting.');
        }

        return $this->totalItems = ($this->counter)();
    }
}
