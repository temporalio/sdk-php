<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Transport\CompletableResult;
use Temporal\Internal\Transport\Request\UpsertTypedSearchAttributes;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ScopedContextInterface;
use Temporal\Internal\Transport\Request\UpsertSearchAttributes;
use Temporal\Workflow\UpdateContext;

class ScopeContext extends WorkflowContext implements ScopedContextInterface
{
    private WorkflowContext $parent;
    private Scope $scope;
    private \Closure $onRequest;
    private ?UpdateContext $updateContext = null;

    /**
     * Creates scope specific context.
     */
    public static function fromWorkflowContext(
        WorkflowContext $context,
        Scope $scope,
        \Closure $onRequest,
        ?UpdateContext $updateContext,
    ): self {
        $ctx = new self(
            $context->services,
            $context->client,
            $context->workflowInstance,
            $context->input,
            $context->getLastCompletionResultValues(),
            $context->handlers,
        );

        $ctx->parent = $context;
        $ctx->scope = $scope;
        $ctx->onRequest = $onRequest;
        $ctx->updateContext = $updateContext;

        return $ctx;
    }

    public function async(callable $handler): CancellationScopeInterface
    {
        return $this->scope->startScope($handler, false);
    }

    public function asyncDetached(callable $handler): CancellationScopeInterface
    {
        return $this->scope->startScope($handler, true);
    }

    public function request(RequestInterface $request, bool $cancellable = true): PromiseInterface
    {
        if ($cancellable && $this->scope->isCancelled()) {
            throw new CanceledFailure('Attempt to send request to cancelled scope');
        }

        $promise = $this->parent->request($request);
        ($this->onRequest)($request, $promise);

        return new CompletableResult(
            $this,
            $this->services->loop,
            $promise,
            $this->scope->getLayer(),
        );
    }

    public function getUpdateContext(): ?UpdateContext
    {
        return $this->updateContext;
    }

    public function resolveConditions(): void
    {
        $this->parent->resolveConditions();
    }

    public function resolveConditionGroup(string $conditionGroupId): void
    {
        $this->parent->resolveConditionGroup($conditionGroupId);
    }

    public function rejectConditionGroup(string $conditionGroupId): void
    {
        $this->parent->rejectConditionGroup($conditionGroupId);
    }

    public function upsertSearchAttributes(array $searchAttributes): void
    {
        $this->request(
            new UpsertSearchAttributes($searchAttributes),
        );
    }

    public function upsertTypedSearchAttributes(SearchAttributeUpdate ...$updates): void
    {
        $this->request(
            new UpsertTypedSearchAttributes($updates),
        );
    }

    #[\Override]
    public function destroy(): void
    {
        parent::destroy();
        unset($this->scope, $this->parent, $this->onRequest);
    }

    protected function addCondition(string $conditionGroupId, callable $condition): PromiseInterface
    {
        $deferred = new Deferred();
        $this->parent->awaits[$conditionGroupId][] = [$condition, $deferred];
        $this->scope->onAwait($deferred);

        return new CompletableResult(
            $this,
            $this->services->loop,
            $deferred->promise(),
            $this->scope->getLayer(),
        );
    }
}
