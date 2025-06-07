<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\Destroyable;

/**
 * @psalm-type Consumer = callable(ValuesInterface): mixed
 *
 * @psalm-type OnSignalCallable = \Closure(non-empty-string $name, Consumer $handler, ValuesInterface $arguments): void
 */
final class SignalQueue implements Destroyable
{
    /**
     * @var array<int, SignalQueueItem>
     */
    private array $queue = [];

    /**
     * @var array<non-empty-string, Consumer>
     */
    private array $consumers = [];

    /**
     * @var OnSignalCallable
     */
    private \Closure $onSignal;

    /**
     * A fallback consumer to handle signals when no consumer is attached.
     *
     * @var null|\Closure(non-empty-string, ValuesInterface): mixed
     */
    private ?\Closure $dynamicConsumer = null;

    /**
     * @param non-empty-string $signal
     */
    public function push(string $signal, ValuesInterface $values): void
    {
        if (isset($this->consumers[$signal])) {
            $this->consume($signal, $values, $this->consumers[$signal]);
            return;
        }

        if ($this->dynamicConsumer !== null) {
            $this->consumeFallback($signal, $values);
            return;
        }

        $this->queue[] = new SignalQueueItem($signal, $values);
    }

    /**
     * @param OnSignalCallable $handler
     */
    public function onSignal(\Closure $handler): void
    {
        $this->onSignal = $handler;
    }

    /**
     * @param non-empty-string $signal
     * @param Consumer $consumer
     */
    public function attach(string $signal, callable $consumer): void
    {
        $this->consumers[$signal] = $consumer; // overwrite

        foreach ($this->queue as $k => $item) {
            if ($item->name === $signal) {
                unset($this->queue[$k]);
                $this->consume($signal, $item->values, $consumer);
            }
        }
    }

    /**
     * @param \Closure(non-empty-string, ValuesInterface): mixed $consumer
     */
    public function setFallback(\Closure $consumer): void
    {
        $this->dynamicConsumer = $consumer;

        // Flush all signals that have no consumer
        foreach ($this->queue as $k => $item) {
            if (\array_key_exists($item->name, $this->consumers)) {
                continue;
            }

            unset($this->queue[$k]);
            $this->consumeFallback($item->name, $item->values);
        }
    }

    public function clear(): void
    {
        $this->queue = [];
    }

    /**
     * @param non-empty-string $signal
     * @param Consumer $consumer
     */
    private function consume(string $signal, ValuesInterface $values, callable $consumer): void
    {
        ($this->onSignal)($signal, $consumer, $values);
    }

    /**
     * @param non-empty-string $signal
     */
    private function consumeFallback(string $signal, ValuesInterface $values): void
    {
        $handler = $this->dynamicConsumer;
        \assert($handler !== null);

        // Wrap the fallback consumer to call interceptors
        $consumer = static fn(ValuesInterface $values): mixed => $handler($signal, $values);
        ($this->onSignal)($signal, $consumer, $values);
    }

    public function destroy(): void
    {
        $this->queue = [];
        $this->consumers = [];
        $this->dynamicConsumer = null;
        unset($this->onSignal);
    }
}
