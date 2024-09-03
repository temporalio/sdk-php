<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\SignalQueue;
use Temporal\Internal\Interceptor;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 * @psalm-type QueryHandler = \Closure(QueryInput): mixed
 * @psalm-type UpdateHandler = \Closure(UpdateInput, Deferred): PromiseInterface
 * @psalm-type ValidateUpdateHandler = \Closure(UpdateInput): void
 * @psalm-type QueryExecutor = \Closure(QueryInput, callable(ValuesInterface): mixed): mixed
 * @psalm-type UpdateExecutor = \Closure(UpdateInput, callable(ValuesInterface): mixed, Deferred): PromiseInterface
 * @psalm-type ValidateUpdateExecutor = \Closure(UpdateInput, callable(ValuesInterface): mixed): mixed
 * @psalm-type UpdateValidator = \Closure(UpdateInput, UpdateHandler): void
 */
final class WorkflowInstance extends Instance implements WorkflowInstanceInterface
{
    /**
     * @var array<non-empty-string, QueryHandler>
     */
    private array $queryHandlers = [];

    /**
     * @var array<non-empty-string, DispatchableHandler>
     */
    private array $signalHandlers = [];

    /**
     * @var array<non-empty-string, UpdateHandler>
     */
    private array $updateHandlers = [];

    /**
     * @var array<non-empty-string, null|ValidateUpdateHandler>
     */
    private array $validateUpdateHandlers = [];

    private SignalQueue $signalQueue;

    /** @var QueryExecutor */
    private \Closure $queryExecutor;

    /** @var UpdateExecutor */
    private \Closure $updateExecutor;

    /** @var ValidateUpdateExecutor */
    private \Closure $updateValidator;

    /**
     * @param object $context Workflow object
     * @param Interceptor\Pipeline<WorkflowInboundCallsInterceptor, mixed> $pipeline
     */
    public function __construct(
        private WorkflowPrototype $prototype,
        object $context,
        private Interceptor\Pipeline $pipeline,
    ) {
        parent::__construct($prototype, $context);

        $this->signalQueue = new SignalQueue();

        foreach ($prototype->getSignalHandlers() as $name => $definition) {
            $this->signalHandlers[$name] = $this->createHandler($definition->method);
            $this->signalQueue->attach($name, $this->signalHandlers[$name]);
        }

        $updateValidators = $prototype->getValidateUpdateHandlers();
        foreach ($prototype->getUpdateHandlers() as $name => $definition) {
            $fn = $this->createHandler($definition->method);
            $this->updateHandlers[$name] = fn(UpdateInput $input, Deferred $deferred): mixed =>
                ($this->updateExecutor)($input, $fn, $deferred);
            // Register validate update handlers
            $this->validateUpdateHandlers[$name] = \array_key_exists($name, $updateValidators)
                ? fn(UpdateInput $input): mixed => ($this->updateValidator)(
                    $input,
                    $this->createHandler($updateValidators[$name]),
                )
                : null;
        }

        foreach ($prototype->getQueryHandlers() as $name => $definition) {
            $fn = $this->createHandler($definition->method);
            $this->queryHandlers[$name] = $this->pipeline->with(
                function (QueryInput $input) use ($fn): mixed {
                    return ($this->queryExecutor)($input, $fn);
                },
                /** @see WorkflowInboundCallsInterceptor::handleQuery() */
                'handleQuery',
            )(...);
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
     * @param UpdateExecutor $executor
     */
    public function setUpdateExecutor(\Closure $executor): self
    {
        $this->updateExecutor = $executor;
        return $this;
    }

    /**
     * @param ValidateUpdateExecutor $validator
     */
    public function setUpdateValidator(\Closure $validator): self
    {
        $this->updateValidator = $validator;
        return $this;
    }

    /**
     * Trigger constructor in Process context.
     */
    public function initConstructor(): void
    {
        if (\method_exists($this->context, '__construct')) {
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
     *
     * @return null|\Closure(QueryInput): mixed
     * @psalm-return QueryHandler|null
     */
    public function findQueryHandler(string $name): ?\Closure
    {
        return $this->queryHandlers[$name] ?? null;
    }

    /**
     * @param non-empty-string $name
     *
     * @return null|\Closure(UpdateInput, Deferred): PromiseInterface
     * @psalm-return UpdateHandler|null
     */
    public function findUpdateHandler(string $name): ?\Closure
    {
        return $this->updateHandlers[$name] ?? null;
    }

    /**
     * @param non-empty-string $name
     *
     * @return null|\Closure(UpdateInput): void
     * @psalm-return ValidateUpdateHandler|null
     */
    public function findValidateUpdateHandler(string $name): ?\Closure
    {
        return $this->validateUpdateHandlers[$name] ?? null;
    }

    /**
     * @param string $name
     * @param callable(ValuesInterface):mixed $handler
     * @throws \ReflectionException
     */
    public function addQueryHandler(string $name, callable $handler): void
    {
        $fn = $this->createCallableHandler($handler);

        $this->queryHandlers[$name] = $this->pipeline->with(
            function (QueryInput $input) use ($fn) {
                return ($this->queryExecutor)($input, $fn);
            },
            /** @see WorkflowInboundCallsInterceptor::handleQuery() */
            'handleQuery',
        )(...);
    }

    /**
     * @param non-empty-string $name
     * @param callable(ValuesInterface):mixed $handler
     * @throws \ReflectionException
     */
    public function addUpdateHandler(string $name, callable $handler): void
    {
        $fn = $this->createCallableHandler($handler);

        $this->updateHandlers[$name] = $this->pipeline->with(
            function (UpdateInput $input, Deferred $deferred) use ($fn) {
                return ($this->updateExecutor)($input, $fn, $deferred);
            },
            /** @see WorkflowInboundCallsInterceptor::handleUpdate() */
            'handleUpdate',
        )(...);
    }

    /**
     * @return string[]
     */
    public function getQueryHandlerNames(): array
    {
        return \array_keys($this->queryHandlers);
    }

    /**
     * @return string[]
     */
    public function getUpdateHandlerNames(): array
    {
        return \array_keys($this->updateHandlers);
    }

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
        $this->updateHandlers = [];
        $this->validateUpdateHandlers = [];
        unset(
            $this->queryExecutor,
            $this->updateExecutor,
            $this->updateValidator,
            $this->prototype,
            $this->pipeline,
        );
        parent::destroy();
    }

    public function getPrototype(): WorkflowPrototype
    {
        return $this->prototype;
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
