<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Exception\CancellationException;
use Temporal\Internal\Workflow\Process\CoroutineScope;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ScopedContextInterface;
use Temporal\Workflow\WorkflowContext;
use Temporal\Workflow\WorkflowContextInterface;

class ScopeContext extends WorkflowContext implements ScopedContextInterface
{
    /**
     * @var CoroutineScope
     */
    private CoroutineScope $scope;

    /**
     * @var callable
     */
    private $onRequest;

    /**
     * Creates scope specific context.
     *
     * @param WorkflowContext $context
     * @param CoroutineScope $scope
     * @param callable $onRequest
     *
     * @return WorkflowContextInterface
     */
    public static function fromWorkflowContext(
        WorkflowContext $context,
        CoroutineScope $scope,
        callable $onRequest
    ): WorkflowContextInterface {
        $ctx = new self($context->services, $context->client, $context->workflowInstance, $context->input);
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
        $this->recordTrace();

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
        $this->recordTrace();

        return $this->scope->createScope($handler, true);
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        if ($this->scope->isCancelled()) {
            throw new CancellationException("Attempt to send request to cancelled scope");
        }

        $promise = parent::request($request);
        ($this->onRequest)($request, $promise);

        return $promise;
    }
}
