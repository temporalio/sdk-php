<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Queue;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Message\RequestInterface;

class RequestQueue implements RequestQueueInterface
{
    /**
     * @psalm-var array<int, EntryInterface>
     *
     * @var EntryInterface[]
     */
    private array $requests = [];

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        while (\count($this->requests)) {
            yield \array_shift($this->requests);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return \count($this->requests) === 0;
    }

    /**
     * {@inheritDoc}
     */
    public function add(RequestInterface $request): PromiseInterface
    {
        $deferred = new Deferred();

        $this->requests[] = $entry = new Entry($request, $deferred);

        return $entry->promise;
    }

    /**
     * {@inheritDoc}
     */
    public function pull(PromiseInterface $promise): ?EntryInterface
    {
        foreach ($this->requests as $i => $entry) {
            if ($entry->promise === $promise) {
                try {
                    return $entry;
                } finally {
                    unset($this->requests[$i]);
                }
            }
        }

        return null;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return \count($this->requests);
    }
}
