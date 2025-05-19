<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Api\Sdk\V1\WorkflowInteractionDefinition;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Destroyable;
use Temporal\Internal\Declaration\MethodHandler;
use Temporal\Internal\Declaration\Prototype\UpdateDefinition;
use Temporal\Internal\Interceptor\Pipeline;

/**
 * @internal
 */
final class UpdateDispatcher implements Destroyable
{
    /**
     * A fallback handler for dynamic update handlers.
     * @var null|\Closure(UpdateInput, Deferred): PromiseInterface
     */
    private ?\Closure $updateDynamicHandler = null;

    /**
     * A fallback validator for dynamic update handlers.
     * @var null|\Closure(UpdateInput): void
     */
    private ?\Closure $updateDynamicValidator = null;

    /** @var array<non-empty-string, UpdateMethod> */
    private array $updateHandlers = [];

    /** @var \Closure(UpdateInput, callable(ValuesInterface): mixed, Deferred): PromiseInterface */
    private \Closure $updateExecutor;

    /** @var \Closure(UpdateInput, callable(ValuesInterface): mixed): void */
    private \Closure $updateValidator;

    /**
     * @param Pipeline<WorkflowInboundCallsInterceptor, mixed> $pipeline Interceptor pipeline.
     * @param object $context Workflow instance.
     */
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly object $context,
    ) {}

    /**
     * @param \Closure(UpdateInput, callable(ValuesInterface): mixed, Deferred): PromiseInterface $executor
     */
    public function setUpdateExecutor(\Closure $executor): self
    {
        $this->updateExecutor = $executor;
        return $this;
    }

    /**
     * @param \Closure(UpdateInput, callable(ValuesInterface): mixed): void $validator
     */
    public function setUpdateValidator(\Closure $validator): self
    {
        $this->updateValidator = $validator;
        return $this;
    }

    /**
     * @param non-empty-string $name
     * @return null|\Closure(UpdateInput, Deferred): PromiseInterface
     */
    public function findUpdateHandler(string $name): ?\Closure
    {
        $method = $this->updateHandlers[$name] ?? null;
        return $method === null
            ? $this->updateDynamicHandler
            : $method->handler;
    }

    /**
     * @param non-empty-string $name
     *
     * @return null|\Closure(UpdateInput): void
     */
    public function findValidateUpdateHandler(string $name): ?\Closure
    {
        $method = $this->updateHandlers[$name] ?? null;
        return $method === null
            ? $this->updateDynamicValidator
            : $method->validator;
    }

    /**
     * @param non-empty-string $name
     *
     * @throws \ReflectionException
     */
    public function addUpdateHandler(string $name, callable $handler, ?callable $validator, string $description): void
    {
        $handler = $this->createHandler(new \ReflectionFunction($handler(...)));
        $validator === null or $validator = $this->createHandler(new \ReflectionFunction($validator(...)));
        $this->updateHandlers[$name] = new UpdateMethod(
            name: $name,
            handler: fn(
                UpdateInput $input,
                Deferred $deferred,
            ): PromiseInterface => ($this->updateExecutor)($input, $handler, $deferred),
            validator: $validator === null
                ? null
                : fn(UpdateInput $input): mixed => ($this->updateValidator)($input, $validator),
            description: $description,
        );
    }

    public function addFromUpdateDefinition(UpdateDefinition $definition): void
    {
        $name = $definition->name;
        $handler = $this->createHandler($definition->method);
        $validator = $definition->validator === null
            ? null
            : $this->createHandler($definition->validator);

        $this->updateHandlers[$name] = new UpdateMethod(
            name: $name,
            handler: fn(
                UpdateInput $input,
                Deferred $deferred,
            ): PromiseInterface => ($this->updateExecutor)($input, $handler, $deferred),
            validator: $validator === null
                ? null
                : fn(UpdateInput $input): mixed => ($this->updateValidator)($input, $validator),
            description: $definition->description,
        );
    }

    /**
     * @return non-empty-string[]
     */
    public function getUpdateHandlerNames(): array
    {
        return \array_keys($this->updateHandlers);
    }

    /**
     * @param callable(non-empty-string, ValuesInterface): mixed $handler
     * @param null|callable(non-empty-string, ValuesInterface): mixed $validator
     */
    public function setDynamicUpdateHandler(callable $handler, ?callable $validator = null): void
    {
        $this->updateDynamicValidator = $validator === null
            ? null
            : fn(UpdateInput $input): mixed => ($this->updateValidator)(
                $input,
                static fn(ValuesInterface $arguments): mixed => $validator($input->updateName, $arguments),
            );

        $this->updateDynamicHandler = fn(
            UpdateInput $input,
            Deferred $deferred,
        ): PromiseInterface => ($this->updateExecutor)(
            $input,
            static fn(ValuesInterface $arguments): mixed => $handler($input->updateName, $arguments),
            $deferred,
        );
    }

    /**
     * @return list<WorkflowInteractionDefinition>
     */
    public function getUpdateHandlers(): array
    {
        /** @var list<WorkflowInteractionDefinition> $handlers */
        $handlers = [];
        foreach ($this->updateHandlers as $handler) {
            $handlers[] = (new WorkflowInteractionDefinition())
                ->setName($handler->name)
                ->setDescription($handler->description);
        }

        if ($this->updateDynamicHandler !== null) {
            $handlers[] = (new WorkflowInteractionDefinition())
                ->setDescription('Dynamic update handler');
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
        $this->updateHandlers = [];
        unset(
            $this->updateExecutor,
            $this->updateValidator,
        );
    }

    protected function createHandler(\ReflectionFunctionAbstract $func): MethodHandler
    {
        return new MethodHandler($this->context, $func);
    }
}
