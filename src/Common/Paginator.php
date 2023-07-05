<?php

declare(strict_types=1);

namespace Temporal\Common;

use Generator;
use IteratorAggregate;
use Traversable;

/**
 * @template TItem
 * @implements IteratorAggregate<TItem>
 */
final class Paginator implements IteratorAggregate
{
    /** @var int<1, max> */
    private int $pageNumber = 1;
    /** @var list<TItem> */
    private array $collection;
    /** @var self<TItem>|null */
    private ?self $nextPage = null;

    /**
     * @param Generator<array-key, list<TItem>> $loader
     */
    public function __construct(
        private Generator $loader,
    ) {
        $this->collection = $loader->current();
    }

    /**
     * Load next page.
     *
     * @return self<TItem>|null
     */
    public function nextPage(): ?self
    {
        if ($this->nextPage !== null) {
            return $this->nextPage;
        }

        $this->loader->next();
        if (!$this->loader->valid()) {
            return null;
        }

        $this->nextPage = new self($this->loader);

        $this->nextPage->pageNumber = $this->pageNumber + 1;
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

            $paginator = $paginator->nextPage();
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
