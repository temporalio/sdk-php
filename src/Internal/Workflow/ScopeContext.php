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
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Transport\CompletableResult;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ScopedContextInterface;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Internal\Transport\Request\UpsertSearchAttributes;

class ScopeContext extends WorkflowContext implements ScopedContextInterface
{
    private WorkflowContext $parent;
    private Scope $scope;
    private $onRequest;

    /**
     * Creates scope specific context.
     *
     * @param WorkflowContext $context
     * @param Scope           $scope
     * @param callable        $onRequest
     *
     * @return WorkflowContextInterface
     */
    public static function fromWorkflowContext(
        WorkflowContext $context,
        Scope $scope,
        callable $onRequest
    ): WorkflowContextInterface {
        $ctx = new self(
            $context->services,
            $context->client,
            $context->workflowInstance,
            $context->input,
            $context->getLastCompletionResultValues()
        );

        $ctx->parent = $context;
        $ctx->scope = $scope;
        $ctx->onRequest = $onRequest;

        return $ctx;
    }

    /**
     * @param callable $handler
     * @return CancellationScopeInterface
     */
    public function async(callable $handler): CancellationScopeInterface
    {
        return $this->scope->startScope($handler, false);
    }

    /**
     * Cancellation scope which does not react to parent cancel and completes in background.
     *
     * @param callable $handler
     * @return CancellationScopeInterface
     */
    public function asyncDetached(callable $handler): CancellationScopeInterface
    {
        return $this->scope->startScope($handler, true);
    }

    /**
     * @param RequestInterface $request
     * @param bool $cancellable
     * @return PromiseInterface
     */
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
            $this->scope->getLayer()
        );
    }

    /**
     * @param string $conditionGroupId
     * @param callable $condition
     * @return PromiseInterface
     */
    protected function addCondition(string $conditionGroupId, callable $condition): PromiseInterface
    {
        $deferred = new Deferred();
        $this->parent->awaits[$conditionGroupId][] = [$condition, $deferred];
        $this->scope->onAwait($deferred);

        return new CompletableResult(
            $this,
            $this->services->loop,
            $deferred->promise(),
            $this->scope->getLayer()
        );
    }

    protected function addAsyncCondition(string $conditionGroupId, PromiseInterface $condition): PromiseInterface
    {
        $this->parent->asyncAwaits[$conditionGroupId][] = $condition;

        return $condition->then(
            function ($result) use ($conditionGroupId) {
                $this->resolveConditionGroup($conditionGroupId);
                return $result;
            },
            function () use ($conditionGroupId) {
                $this->rejectConditionGroup($conditionGroupId);
            }
        );
    }

    /**
     * Calculate unblocked conditions.
     */
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

    /**
     * {@inheritDoc}
     */
    public function timer($interval): PromiseInterface
    {
        $request = new NewTimer(DateInterval::parse($interval, DateInterval::FORMAT_SECONDS));
        $result = $this->request($request);
        $this->parent->timers->attach($result, $request);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function upsertSearchAttributes(array $searchAttributes): void
    {
        $this->request(
            new UpsertSearchAttributes($searchAttributes)
        );
    }
}
