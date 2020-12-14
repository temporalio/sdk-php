<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Events;

/**
 * @mixin EventEmitterInterface
 * @mixin EventListenerInterface
 *
 * @template-covariant T of string
 *
 * @template-implements EventEmitterInterface<T>
 * @template-implements EventListenerInterface<T>
 */
trait EventEmitterTrait
{
    /**
     * @var array<string, array<callable>>
     */
    protected array $events = [];

    /**
     * @var array<string, array<callable>>
     */
    protected array $once = [];

    /**
     * {@inheritDoc}
     */
    public function on(string $event, callable $then): self
    {
        $this->events[$event][] = $then;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function once(string $event, callable $then): self
    {
        $this->once[$event][] = $then;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function emit(string $event, array $arguments = []): void
    {
        foreach ($this->events[$event] ?? [] as $callback) {
            $callback(...$arguments);
        }

        while (($this->once[$event] ?? []) !== []) {
            $callback = \array_shift($this->once[$event]);
            $callback(...$arguments);
        }

        unset($this->once[$event]);
    }
}
