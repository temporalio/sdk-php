<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\SignalQueue;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 */
final class WorkflowInstance extends Instance implements WorkflowInstanceInterface
{
    /**
     * @var array<string, DispatchableHandler>
     */
    private array $queryHandlers = [];

    /**
     * @var array<string, DispatchableHandler>
     */
    private array $signalHandlers = [];

    /**
     * @var SignalQueue
     */
    private SignalQueue $signalQueue;

    /**
     * @param WorkflowPrototype $prototype
     * @param object $context
     */
    public function __construct(WorkflowPrototype $prototype, object $context)
    {
        parent::__construct($prototype, $context);

        $this->signalQueue = new SignalQueue();

        foreach ($prototype->getSignalHandlers() as $method => $reflection) {
            $this->signalHandlers[$method] = $this->createHandler($reflection);
            $this->signalQueue->attach($method, $this->signalHandlers[$method]);
        }

        foreach ($prototype->getQueryHandlers() as $method => $reflection) {
            $this->queryHandlers[$method] = $this->createHandler($reflection);
        }
    }

    /**
     * Trigger constructor in Process context.
     */
    public function initConstructor(): void
    {
        if (method_exists($this->context, '__construct')) {
            $this->context->__construct();
        }
    }

    /**
     * @return SignalQueue
     */
    public function getSignalQueue(): SignalQueue
    {
        return $this->signalQueue;
    }

    /**
     * @param string $name
     * @return \Closure|null
     */
    public function findQueryHandler(string $name): ?\Closure
    {
        return $this->queryHandlers[$name] ?? null;
    }

    /**
     * @param string $name
     * @param callable $handler
     * @throws \ReflectionException
     */
    public function addQueryHandler(string $name, callable $handler): void
    {
        $this->queryHandlers[$name] = $this->createCallableHandler($handler);
    }

    /**
     * @return string[]
     */
    public function getQueryHandlerNames(): array
    {
        return \array_keys($this->queryHandlers);
    }

    /**
     * @param string $name
     * @return \Closure
     */
    public function getSignalHandler(string $name): \Closure
    {
        return fn (ValuesInterface $values) => $this->signalQueue->push($name, $values);
    }

    /**
     * @param string $name
     * @param callable $handler
     * @throws \ReflectionException
     */
    public function addSignalHandler(string $name, callable $handler): void
    {
        $this->signalHandlers[$name] = $this->createCallableHandler($handler);
        $this->signalQueue->attach($name, $this->signalHandlers[$name]);
    }
}
