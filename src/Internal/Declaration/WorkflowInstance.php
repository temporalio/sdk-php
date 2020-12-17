<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

use JetBrains\PhpStorm\Pure;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;

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
     * @param WorkflowPrototype $prototype
     * @param object $context
     */
    public function __construct(WorkflowPrototype $prototype, object $context)
    {
        parent::__construct($prototype, $context);

        foreach ($prototype->getSignalHandlers() as $method => $reflection) {
            $this->signalHandlers[$method] = $this->createHandler($reflection);
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
     * @return \Closure|null
     */
    public function findSignalHandler(string $name): ?\Closure
    {
        return $this->signalHandlers[$name] ?? null;
    }

    /**
     * @param string $name
     * @param callable $handler
     */
    public function addSignalHandler(string $name, callable $handler): void
    {
        $this->signalHandlers[$name] = \Closure::fromCallable($handler);
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
