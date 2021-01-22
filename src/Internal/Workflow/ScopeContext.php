<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Transport\CompletableResult;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ScopedContextInterface;
use Temporal\Workflow\WorkflowContext;
use Temporal\Workflow\WorkflowContextInterface;

class ScopeContext extends WorkflowContext implements ScopedContextInterface
{
    private WorkflowContext $parent;
    private Scope $scope;
    private $onRequest;

    /**
     * Creates scope specific context.
     *
     * @param WorkflowContext $context
     * @param Scope $scope
     * @param callable $onRequest
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
    public function newCancellationScope(callable $handler): CancellationScopeInterface
    {
        return $this->scope->createScope($handler, false);
    }

    /**
     * Cancellation scope which does not react to parent cancel and completes in background.
     *
     * @param callable $handler
     * @return CancellationScopeInterface
     */
    public function newDetachedCancellationScope(callable $handler): CancellationScopeInterface
    {
        return $this->scope->createScope($handler, true);
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        if ($this->scope->isCancelled()) {
            throw new CanceledFailure("Attempt to send request to cancelled scope");
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
     * @param callable $condition
     * @return PromiseInterface
     */
    public function addCondition(callable $condition): PromiseInterface
    {
        return new CompletableResult(
            $this,
            $this->services->loop,
            $this->parent->addCondition($condition),
            $this->scope->getLayer()
        );
    }
}
