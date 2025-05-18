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
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Destroyable;
use Temporal\Internal\Declaration\MethodHandler;
use Temporal\Internal\Declaration\Prototype\QueryDefinition;
use Temporal\Internal\Interceptor\Pipeline;

/**
 * @psalm-type QueryHandler = \Closure(QueryInput): mixed
 * @psalm-type QueryExecutor = \Closure(QueryInput, callable(ValuesInterface): mixed): mixed
 *
 * @internal
 */
final class QueryDispatcher implements Destroyable
{
    /** @var array<non-empty-string, QueryMethod> */
    private array $queryHandlers = [];

    /** @var null|QueryHandler */
    private ?\Closure $queryDynamicHandler = null;

    /** @var QueryExecutor */
    private \Closure $queryExecutor;

    /**
     * @param Pipeline<WorkflowInboundCallsInterceptor, mixed> $pipeline Interceptor pipeline.
     * @param object $context Workflow instance.
     */
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly object $context,
    ) {}

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
     * @param non-empty-string $name
     *
     * @return null|\Closure(QueryInput): mixed
     * @psalm-return QueryHandler|null
     */
    public function findQueryHandler(string $name): ?\Closure
    {
        return $this->queryHandlers[$name]?->handler ?? $this->queryDynamicHandler;
    }

    /**
     * @param non-empty-string $name
     * @throws \ReflectionException
     */
    public function addQueryHandler(string $name, callable $handler, string $description): void
    {
        $handler = new MethodHandler($this->context, new \ReflectionFunction($handler(...)));
        $this->queryHandlers[$name] = new QueryMethod(
            $name,
            $this->pipeline->with(
                fn(QueryInput $input): mixed => ($this->queryExecutor)($input, $handler),
                /** @see WorkflowInboundCallsInterceptor::handleQuery() */
                'handleQuery',
            )(...),
            $description,
        );
    }

    public function addFromQueryDefinition(QueryDefinition $definition): void
    {
        $handler = new MethodHandler($this->context, $definition->method);

        $this->queryHandlers[$definition->name] = new QueryMethod(
            $definition->name,
            $this->pipeline->with(
                fn(QueryInput $input): mixed => ($this->queryExecutor)($input, $handler),
                /** @see WorkflowInboundCallsInterceptor::handleQuery() */
                'handleQuery',
            )(...),
            $definition->description,
        );
    }

    /**
     * @return list<non-empty-string>
     */
    public function getQueryHandlerNames(): array
    {
        return \array_keys($this->queryHandlers);
    }

    /**
     * @return list<WorkflowInteractionDefinition>
     */
    public function getQueryHandlers(): array
    {
        return []; // todo
    }

    public function setDynamicQueryHandler(callable $handler): void
    {
        $this->queryDynamicHandler = $this->pipeline->with(
            fn(QueryInput $input): mixed => ($this->queryExecutor)(
                $input,
                static fn(ValuesInterface $arguments): mixed => $handler($input->queryName, $arguments),
            ),
            /** @see WorkflowInboundCallsInterceptor::handleQuery() */
            'handleQuery',
        )(...);
    }

    public function destroy(): void
    {
        $this->queryHandlers = [];
        unset(
            $this->queryExecutor,
            $this->queryDynamicHandler,
        );
    }
}
