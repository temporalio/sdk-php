<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Temporal\Internal\Support\Options;

class OptionsReducer implements \Iterator
{
    /**
     * @var \Traversable<Options|null>
     */
    private \Traversable $iterator;

    /**
     * @param iterable<Options|null> $iterator
     */
    public function __construct(iterable $iterator)
    {
        $this->iterator = $this->fromOuterIterator($iterator);
    }

    /**
     * @param iterable<Options|null> $iterator
     * @return \Traversable<Options|null>
     */
    private function fromOuterIterator(iterable $iterator): \Traversable
    {
        $current = null;

        foreach ($iterator as $option) {
            if ($option === null) {
                yield $current;

                continue;
            }

            yield $current = ($current === null ? $option : $current->mergeWith($option));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return $this->iterator->current();
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        $this->iterator->next();
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        $this->iterator->rewind();
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->iterator->key();
    }
}
