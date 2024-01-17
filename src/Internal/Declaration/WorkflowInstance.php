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
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\SignalQueue;
use Temporal\Internal\Interceptor;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 * @psalm-type QueryHandler = \Closure(QueryInput): mixed
 * @psalm-type QueryExecutor = \Closure(QueryInput, callable(ValuesInterface): mixed): mixed
 */
final class WorkflowInstance extends Instance implements WorkflowInstanceInterface, Destroyable
{
    /**
     * @var array<non-empty-string, QueryHandler>
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

    /** @var QueryExecutor */
    private \Closure $queryExecutor;

    /**
     * @param WorkflowPrototype $prototype
     * @param object $context
     * @param Interceptor\Pipeline<WorkflowInboundCallsInterceptor, mixed> $pipeline
     */
    public function __construct(
        WorkflowPrototype $prototype,
        object $context,
        private Interceptor\Pipeline $pipeline,
    ) {
        parent::__construct($prototype, $context);

        $this->signalQueue = new SignalQueue();

        foreach ($prototype->getSignalHandlers() as $method => $reflection) {
            $this->signalHandlers[$method] = $this->createHandler($reflection);
            $this->signalQueue->attach($method, $this->signalHandlers[$method]);
        }

        foreach ($prototype->getQueryHandlers() as $method => $reflection) {
            $fn = $this->createHandler($reflection);
            $this->queryHandlers[$method] = \Closure::fromCallable($this->pipeline->with(
                function (QueryInput $input) use ($fn) {
                    return ($this->queryExecutor)($input, $fn);
                },
                /** @see WorkflowInboundCallsInterceptor::handleQuery() */
                'handleQuery',
            ));
        }
    }

    /**
     * @param QueryExecutor $executor
     *
     * @return $this
     */
    public function setQueryExecutor(\Closure $executor): self
    {
        $this->queryExecutor = $executor;
        return $this;
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
     * @param non-empty-string $name
     * @return null|\Closure(ValuesInterface):mixed
     *
     * @psalm-return QueryHandler|null
     */
    public function findQueryHandler(string $name): ?\Closure
    {
        return $this->queryHandlers[$name] ?? null;
    }

    /**
     * @param string $name
     * @param callable(ValuesInterface):mixed $handler
     * @throws \ReflectionException
     */
    public function addQueryHandler(string $name, callable $handler): void
    {
        $fn = $this->createCallableHandler($handler);

        $this->queryHandlers[$name] = \Closure::fromCallable($this->pipeline->with(
            function (QueryInput $input) use ($fn) {
                return ($this->queryExecutor)($input, $fn);
            },
            /** @see WorkflowInboundCallsInterceptor::handleQuery() */
            'handleQuery',
        ));
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

    public function clearSignalQueue(): void
    {
        $this->signalQueue->clear();
    }

    public function destroy(): void
    {
        $this->signalQueue->clear();
        $this->signalHandlers = [];
        $this->queryHandlers = [];
        unset($this->queryExecutor);
    }

    /**
     * Make a Closure from a callable.
     *
     * @param callable $handler
     *
     * @return \Closure(ValuesInterface): mixed
     * @throws \ReflectionException
     *
     * @psalm-return DispatchableHandler
     */
    protected function createCallableHandler(callable $handler): \Closure
    {
        return $this->createHandler(
            new \ReflectionFunction($handler instanceof \Closure ? $handler : \Closure::fromCallable($handler)),
        );
    }
}
