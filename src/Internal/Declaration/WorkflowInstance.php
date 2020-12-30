<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use JetBrains\PhpStorm\Pure;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\SignalQueue;

/**
 * @psalm-import-type DispatchableHandler from WorkflowInstanceInterface
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
     */
    public function addQueryHandler(string $name, callable $handler): void
    {
        $this->queryHandlers[$name] = \Closure::fromCallable($handler);
    }

    /**
     * @return string[]
     */
    #[Pure]
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
        return fn(array $args) => $this->signalQueue->push($name, $args);
    }

    /**
     * @param string $name
     * @param callable $handler
     */
    public function addSignalHandler(string $name, callable $handler): void
    {
        $this->signalHandlers[$name] = \Closure::fromCallable($handler);
        $this->signalQueue->attach($name, $this->signalHandlers[$name]);
    }

    /**
     * @return string[]
     */
    #[Pure]
    public function getSignalHandlerNames(): array
    {
        return \array_keys($this->signalHandlers);
    }
}
