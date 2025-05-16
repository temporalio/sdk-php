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
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalQueue;
use Temporal\Internal\Interceptor;

/**
 * @psalm-type UpdateHandler = \Closure(UpdateInput, Deferred): mixed
 * @psalm-type ValidateUpdateHandler = \Closure(UpdateInput): void
 * @psalm-type UpdateExecutor = \Closure(UpdateInput, callable(ValuesInterface): mixed, Deferred): PromiseInterface
 * @psalm-type ValidateUpdateExecutor = \Closure(UpdateInput, callable(ValuesInterface): mixed): void
 * @psalm-type UpdateValidator = \Closure(UpdateInput, UpdateHandler): void
 *
 * @internal
 */
final class WorkflowInstance extends Instance implements WorkflowInstanceInterface
{
    public readonly QueryDispatcher $queryDispatcher;

    /** @var null|UpdateHandler */
    private ?\Closure $updateDynamicHandler = null;

    /** @var null|ValidateUpdateHandler */
    private ?\Closure $updateDynamicValidator = null;

    /** @var array<non-empty-string, MethodHandler> */
    private array $signalHandlers = [];

    /** @var array<non-empty-string, UpdateHandler> */
    private array $updateHandlers = [];

    /** @var array<non-empty-string, null|ValidateUpdateHandler> */
    private array $validateUpdateHandlers = [];

    private SignalQueue $signalQueue;

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

        $this->queryDispatcher = new QueryDispatcher($pipeline, $context);
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

        foreach ($prototype->getQueryHandlers() as $definition) {
            $this->queryDispatcher->addFromQueryDefinition($definition);
        }
    }

    public function getQueryDispatcher(): QueryDispatcher
    {
        return $this->queryDispatcher;
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
    public function init(array $arguments = []): void
    {
        if (!\method_exists($this->context, '__construct')) {
            return;
        }

        $this->context->__construct(...$arguments);
    }

    public function getSignalQueue(): SignalQueue
    {
        return $this->signalQueue;
    }

    /**
     * @param non-empty-string $name
     *
     * @return null|\Closure(UpdateInput, Deferred): PromiseInterface
     * @psalm-return UpdateHandler|null
     */
    public function findUpdateHandler(string $name): ?\Closure
    {
        return $this->updateHandlers[$name] ?? $this->updateDynamicHandler;
    }

    /**
     * @param non-empty-string $name
     *
     * @return null|\Closure(UpdateInput): void
     * @psalm-return ValidateUpdateHandler|null
     */
    public function findValidateUpdateHandler(string $name): ?\Closure
    {
        return $this->validateUpdateHandlers[$name] ?? (
            \array_key_exists($name, $this->updateHandlers)
                ? null
                : $this->updateDynamicValidator
        );
    }

    /**
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
     * @throws \ReflectionException
     */
    public function addValidateUpdateHandler(string $name, callable $handler): void
    {
        $fn = $this->createCallableHandler($handler);
        $this->validateUpdateHandlers[$name] = fn(UpdateInput $input): mixed => ($this->updateValidator)($input, $fn);
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
        return fn(ValuesInterface $values) => $this->signalQueue->push($name, $values);
    }

    public function addSignalHandler(string $name, callable $handler): void
    {
        $this->signalHandlers[$name] = $this->createCallableHandler($handler);
        $this->signalQueue->attach($name, $this->signalHandlers[$name]);
    }

    public function setDynamicSignalHandler(callable $handler): void
    {
        $this->signalQueue->setFallback($handler(...));
    }

    public function setDynamicUpdateHandler(callable $handler, ?callable $validator = null): void
    {
        $this->updateDynamicValidator = $validator === null
            ? null
            : fn(UpdateInput $input): mixed => ($this->updateValidator)(
                $input,
                static fn(ValuesInterface $arguments): mixed => $validator($input->updateName, $arguments),
            );

        $this->updateDynamicHandler =
            fn(UpdateInput $input, Deferred $deferred): mixed => ($this->updateExecutor)(
                $input,
                static fn(ValuesInterface $arguments): mixed => $handler($input->updateName, $arguments),
                $deferred,
            );
    }

    public function clearSignalQueue(): void
    {
        $this->signalQueue->clear();
    }

    public function destroy(): void
    {
        $this->signalQueue->clear();
        $this->signalHandlers = [];
        $this->updateHandlers = [];
        $this->validateUpdateHandlers = [];
        unset(
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
     * @throws \ReflectionException
     */
    protected function createCallableHandler(callable $handler): MethodHandler
    {
        return $this->createHandler(
            new \ReflectionFunction($handler instanceof \Closure ? $handler : \Closure::fromCallable($handler)),
        );
    }
}
