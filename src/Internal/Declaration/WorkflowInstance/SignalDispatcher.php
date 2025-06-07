<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

use Temporal\Api\Sdk\V1\WorkflowInteractionDefinition;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\Destroyable;
use Temporal\Internal\Declaration\MethodHandler;
use Temporal\Internal\Declaration\Prototype\SignalDefinition;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;

/**
 * @internal
 */
final class SignalDispatcher implements Destroyable
{
    /** @var array<non-empty-string, SignalMethod> */
    private array $signalHandlers = [];

    private readonly SignalQueue $signalQueue;

    /**
     * @param object $context Workflow instance.
     */
    public function __construct(
        WorkflowPrototype $prototype,
        private readonly object $context,
    ) {
        $this->signalQueue = new SignalQueue();

        foreach ($prototype->getSignalHandlers() as $definition) {
            $this->addFromSignalDefinition($definition);
        }
    }

    public function getSignalQueue(): SignalQueue
    {
        return $this->signalQueue;
    }

    /**
     * @param non-empty-string $name
     * @return \Closure(ValuesInterface): void
     */
    public function getSignalHandler(string $name): \Closure
    {
        return fn(ValuesInterface $values) => $this->signalQueue->push($name, $values);
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
        $this->signalQueue->attach($name, $handler);
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
        $this->signalQueue->attach($name, $handler);
    }

    /**
     * @param callable(non-empty-string, ValuesInterface): mixed $handler
     */
    public function setDynamicSignalHandler(callable $handler): void
    {
        $this->signalQueue->setFallback($handler(...));
    }

    public function clearSignalQueue(): void
    {
        $this->signalQueue->clear();
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

        // todo
        // if ($this->dynamic !== null) {
        //     $handlers[] = (new WorkflowInteractionDefinition())
        //         ->setDescription('Dynamic signal handler');
        // }

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
        $this->signalQueue->clear();
        $this->signalQueue->destroy();
        $this->signalHandlers = [];
    }
}
