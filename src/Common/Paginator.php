<?php

declare(strict_types=1);

namespace Temporal\Common;

use Generator;
use IteratorAggregate;
use Traversable;

/**
 * Paginator that allows to iterate over all pages.
 *
 * @template TItem
 * @implements IteratorAggregate<TItem>
 */
final class Paginator implements IteratorAggregate
{
    /** @var list<TItem> */
    private array $collection;
    /** @var self<TItem>|null */
    private ?self $nextPage = null;

    /**
     * @param Generator<array-key, list<TItem>> $loader
     * @param int<1, max> $pageNumber
     */
    private function __construct(
        private Generator $loader,
        private int $pageNumber,
    ) {
        $this->collection = $loader->current();
    }

    /**
     * @template TInitItem
     *
     * @param Generator<array-key, list<TInitItem>> $loader
     *
     * @return self<TInitItem>
     */
    public static function createFromGenerator(Generator $loader): self
    {
        return new self($loader, 1);
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

        return $this->nextPage = new self($this->loader, $this->pageNumber + 1);
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
}
