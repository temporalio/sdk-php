<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use JetBrains\PhpStorm\Pure;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Interceptor\Pipeline;
use Temporal\Interceptor\WorkflowInboundInterceptor;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow\ProcessInterface;

class Process extends Scope implements ProcessInterface
{
    /**
     * Process constructor.
     * @param ServiceContainer $services
     * @param WorkflowContext  $ctx
     */
    public function __construct(ServiceContainer $services, WorkflowContext $ctx)
    {
        parent::__construct($services, $ctx);

        $inboundPipeline = Pipeline::prepare(
            $services->interceptorProvider->getInterceptors(WorkflowInboundInterceptor::class),
        );

        $this->getWorkflowInstance()->getSignalQueue()->onSignal(
            function (string $name, callable $handler, ValuesInterface $arguments) use ($inboundPipeline): void {
                $scope = $this->createScope(true, LoopInterface::ON_SIGNAL);
                $scope->onClose(
                    function (?\Throwable $error): void {
                        if ($error !== null) {
                            // we want to fail process when signal scope fails
                            $this->complete($error);
                        }
                    }
                );

                try {
                    // $scope->start($handler, $arguments);
                    /** @see WorkflowInboundInterceptor::handleSignal() */
                    $inboundPipeline->with(
                        static fn() => $scope->start($handler, $arguments),
                        'handleSignal',
                    )($this->scopeContext, $name);
                } catch (InvalidArgumentException) {
                    // invalid signal invocation, destroy the scope with no traces
                }
            }
        );

        // unlike other scopes Process will notify the server when complete instead of pushing the result
        // to parent scope (there are no parent scope)
        $this->promise()->then(
            function ($result): void {
                $this->complete([$result]);
            },
            function (\Throwable $e): void {
                $this->complete($e);
            }
        );
    }

    /**
     * @param callable             $handler
     * @param ValuesInterface|null $values
     */
    public function start(callable $handler, ValuesInterface $values = null): void
    {
        try {
            $this->makeCurrent();
            $this->context->getWorkflowInstance()->initConstructor();
            parent::start($handler, $values);
        } catch (\Throwable $e) {
            $this->complete($e);
        }
    }

    /**
     * @return mixed|string
     */
    public function getID()
    {
        return $this->context->getRunId();
    }

    /**
     * @return WorkflowInstanceInterface
     */
    #[Pure]
    public function getWorkflowInstance(): WorkflowInstanceInterface
    {
        return $this->getContext()->getWorkflowInstance();
    }

    /**
     * @param $result
     */
    protected function complete($result): void
    {
        if ($result instanceof \Throwable) {
            if ($result instanceof DestructMemorizedInstanceException) {
                // do not handle
                return;
            }

            if ($this->services->exceptionInterceptor->isRetryable($result)) {
                $this->scopeContext->panic($result);
                return;
            }

            $this->scopeContext->complete([], $result);
            return;
        }

        if ($this->scopeContext->isContinuedAsNew()) {
            return;
        }

        $this->scopeContext->complete($result);
    }
}
