<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Events;

/**
 * @mixin EventEmitterInterface
 * @mixin EventListenerInterface
 *
 * @template T of string
 *
 * @template-implements EventEmitterInterface<T>
 * @template-implements EventListenerInterface<T>
 */
trait EventEmitterTrait
{
    /**
     * @var array<T, \SplQueue<callable>>
     */
    protected array $once = [];

    public function once(string $event, callable $then): static
    {
        ($this->once[$event] ??= new \SplQueue())->enqueue($then);

        return $this;
    }

    public function emit(string $event, array $arguments = []): void
    {
        // Re-read: a nested emit of the same event drops the queue, a later once() makes a new one.
        while (true) {
            $queue = $this->once[$event] ?? null;

            if ($queue === null || $queue->isEmpty()) {
                unset($this->once[$event]);
                return;
            }

            $callback = $queue->dequeue();
            $callback(...$arguments);
        }
    }
}
