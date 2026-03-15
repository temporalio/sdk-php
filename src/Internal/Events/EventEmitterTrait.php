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
     * @var array<T, array<callable>>
     */
    protected array $once = [];

    public function once(string $event, callable $then): static
    {
        $this->once[$event][] = $then;

        return $this;
    }

    public function emit(string $event, array $arguments = []): void
    {
        if (!\array_key_exists($event, $this->once)) {
            return;
        }
        while (!empty($this->once[$event])) {
            $callback = \array_shift($this->once[$event]);
            $callback(...$arguments);
        }

        unset($this->once[$event]);
    }
}
