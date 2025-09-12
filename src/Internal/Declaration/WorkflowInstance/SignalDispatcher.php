<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

use Internal\Destroy\Destroyable;
use Temporal\Api\Sdk\V1\WorkflowInteractionDefinition;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\MethodHandler;
use Temporal\Internal\Declaration\Prototype\SignalDefinition;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;

/**
 * @psalm-type Consumer = callable(ValuesInterface): mixed
 * @psalm-type OnSignalCallable = \Closure(non-empty-string $name, Consumer $handler, ValuesInterface $arguments): void
 *
 * @internal
 */
final class SignalDispatcher implements Destroyable
{
    /** @var array<non-empty-string, SignalMethod> */
    private array $signalHandlers = [];

    /**
     * @var OnSignalCallable
     */
    private \Closure $onSignal;

    /**
     * @var array<int, SignalQueueItem>
     */
    private array $queue = [];

    /**
     * @var array<non-empty-string, Consumer>
     */
    private array $consumers = [];

    /**
     * A fallback consumer to handle signals when no consumer is attached.
     *
     * @var null|\Closure(non-empty-string, ValuesInterface): mixed
     */
    private ?\Closure $dynamicConsumer = null;

    /**
     * @param object $context Workflow instance.
     */
    public function __construct(
        WorkflowPrototype $prototype,
        private readonly object $context,
    ) {
        foreach ($prototype->getSignalHandlers() as $definition) {
            $this->addFromSignalDefinition($definition);
        }
    }

    /**
     * @param OnSignalCallable $handler
     */
    public function onSignal(\Closure $handler): void
    {
        $this->onSignal = $handler;
    }

    /**
     * @param non-empty-string $name
     * @return \Closure(ValuesInterface): void
     */
    public function getSignalHandler(string $name): \Closure
    {
        return fn(ValuesInterface $values) => $this->push($name, $values);
    }

    /**
     * @param non-empty-string $name
     */
    public function addSignalHandler(string $name, callable $handler, string $description): void
    {
        $handler = new MethodHandler($this->context, new \ReflectionFunction($handler(...)));
        $this->signalHandlers[$name] = new SignalMethod(
            $name,
            $handler,
            $description,
        );
        $this->attach($name, $handler);
    }

    public function addFromSignalDefinition(SignalDefinition $definition): void
    {
        $name = $definition->name;
        $handler = new MethodHandler($this->context, $definition->method);
        $this->signalHandlers[$name] = new SignalMethod(
            $name,
            $handler,
            $definition->description,
        );
        $this->attach($name, $handler);
    }

    /**
     * @param callable(non-empty-string, ValuesInterface): mixed $handler
     */
    public function setDynamicSignalHandler(callable $handler): void
    {
        $consumer = $handler(...);

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

    public function clearSignalQueue(): void
    {
        $this->queue = [];
    }

    /**
     * @return list<WorkflowInteractionDefinition>
     */
    public function getSignalHandlers(): array
    {
        /** @var list<WorkflowInteractionDefinition> $handlers */
        $handlers = [];
        foreach ($this->signalHandlers as $handler) {
            $handlers[] = (new WorkflowInteractionDefinition())
                ->setName($handler->name)
                ->setDescription($handler->description);
        }

        if ($this->dynamicConsumer !== null) {
            $handlers[] = (new WorkflowInteractionDefinition())
                ->setDescription('Dynamic signal handler');
        }

        \usort(
            $handlers,
            static fn(
                WorkflowInteractionDefinition $a,
                WorkflowInteractionDefinition $b,
            ): int => $a->getName() <=> $b->getName(),
        );

        return $handlers;
    }

    public function destroy(): void
    {
        $this->queue = [];
        $this->consumers = [];
        $this->dynamicConsumer = null;
        unset($this->onSignal);
        $this->signalHandlers = [];
    }

    /**
     * @param non-empty-string $signal
     */
    private function push(string $signal, ValuesInterface $values): void
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
     * @param non-empty-string $signal
     * @param Consumer $consumer
     */
    private function attach(string $signal, callable $consumer): void
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
}
