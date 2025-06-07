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
use Temporal\Internal\Declaration\Destroyable;
use Temporal\Internal\Declaration\MethodHandler;
use Temporal\Internal\Declaration\Prototype\QueryDefinition;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;

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
     * @param object $context Workflow instance.
     */
    public function __construct(
        WorkflowPrototype $prototype,
        private readonly object $context,
    ) {
        foreach ($prototype->getQueryHandlers() as $definition) {
            $this->addFromQueryDefinition($definition);
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
            fn(QueryInput $input): mixed => ($this->queryExecutor)($input, $handler),
            $description,
        );
    }

    public function addFromQueryDefinition(QueryDefinition $definition): void
    {
        $handler = new MethodHandler($this->context, $definition->method);

        $this->queryHandlers[$definition->name] = new QueryMethod(
            $definition->name,
            fn(QueryInput $input): mixed => ($this->queryExecutor)($input, $handler),
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
        /** @var list<WorkflowInteractionDefinition> $handlers */
        $handlers = [];
        foreach ($this->queryHandlers as $handler) {
            $handlers[] = (new WorkflowInteractionDefinition())
                ->setName($handler->name)
                ->setDescription($handler->description);
        }

        if ($this->queryDynamicHandler !== null) {
            $handlers[] = (new WorkflowInteractionDefinition())
                ->setDescription('Dynamic query handler');
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

    /**
     * @param callable(non-empty-string, ValuesInterface): mixed $handler
     */
    public function setDynamicQueryHandler(callable $handler): void
    {
        $this->queryDynamicHandler = fn(QueryInput $input): mixed => ($this->queryExecutor)(
            $input,
            static fn(ValuesInterface $arguments): mixed => $handler($input->queryName, $arguments),
        );
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
