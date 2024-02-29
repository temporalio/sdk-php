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
use React\Promise\PromiseInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow;
use Temporal\Workflow\ProcessInterface;

/**
 * Root process scope.
 *
 * @implements ProcessInterface<mixed>
 */
class Process extends Scope implements ProcessInterface
{
    /**
     * Process constructor.
     * @param ServiceContainer $services
     * @param WorkflowContext  $ctx
     */
    public function __construct(
        ServiceContainer $services,
        WorkflowContext $ctx,
        private string $runId,
    ) {
        parent::__construct($services, $ctx);

        $inboundPipeline = $services->interceptorProvider->getPipeline(WorkflowInboundCallsInterceptor::class);
        $wfInstance = $this->getWorkflowInstance();
        \assert($wfInstance instanceof WorkflowInstance);

        // Configure query handler in an immutable scope
        $wfInstance->setQueryExecutor(function (QueryInput $input, callable $handler): mixed {
            try {
                $context = $this->scopeContext->withInput(
                    new Input(
                        $this->scopeContext->getInfo(),
                        $input->arguments,
                    )
                );
                Workflow::setCurrentContext($context);

                return $handler($input->arguments);
            } finally {
                Workflow::setCurrentContext(null);
            }
        });

        // Configure update validator in an immutable scope
        $wfInstance->setUpdateValidator(function (UpdateInput $input, callable $handler) use ($inboundPipeline): void {
            try {
                Workflow::setCurrentContext($this->scopeContext);
                $inboundPipeline->with(
                    function (UpdateInput $input) use ($handler): void {
                        Workflow::setCurrentContext($this->scopeContext->withInput(
                            new Input(
                                $this->scopeContext->getInfo(),
                                $input->arguments,
                                $input->header,
                            )
                        ));
                        $handler($input->arguments);
                    },
                    /** @see WorkflowInboundCallsInterceptor::validateUpdate() */
                    'validateUpdate',
                )($input);
            } finally {
                Workflow::setCurrentContext(null);
            }
        });

        // Configure update handler in a mutable scope
        $wfInstance->setUpdateExecutor(function (UpdateInput $input, callable $handler) use ($inboundPipeline): PromiseInterface {
            try {
                // Define Context for interceptors Pipeline
                $scope = $this->createScope(
                    detached: true,
                    layer: LoopInterface::ON_TICK,
                    context: $this->context->withInput(
                        new Input($input->info, $input->arguments, $input->header),
                    ),
                );
                $scope->startSignal(
                    function () use ($handler, $inboundPipeline, $input) {
                        Workflow::setCurrentContext($this->scopeContext);
                        return $inboundPipeline->with(
                            function (UpdateInput $input) use ($handler) {
                                return $handler($input->arguments);
                            },
                            /** @see WorkflowInboundCallsInterceptor::handleUpdate() */
                            'handleUpdate',
                        )($input);
                    },
                    $input->arguments,
                );

                return $scope->promise();
            } finally {
                Workflow::setCurrentContext(null);
            }
        });

        // Configure signal handler
        $wfInstance->getSignalQueue()->onSignal(
            function (string $name, callable $handler, ValuesInterface $arguments) use ($inboundPipeline): void {
                // Define Context for interceptors Pipeline
                Workflow::setCurrentContext($this->scopeContext);

                $inboundPipeline->with(
                    function (SignalInput $input) use ($handler) {
                        $this->createScope(
                            true,
                            LoopInterface::ON_SIGNAL,
                            $this->context->withInput(
                                new Input($input->info, $input->arguments, $input->header),
                            ),
                        )->onClose(
                            function (?\Throwable $error): void {
                                if ($error !== null) {
                                    // we want to fail process when signal scope fails
                                    $this->complete($error);
                                }
                            }
                        )->startSignal(
                            $handler,
                            $input->arguments
                        );
                    },
                    /** @see WorkflowInboundCallsInterceptor::handleSignal() */
                    'handleSignal',
                )(new SignalInput(
                    $name,
                    $this->scopeContext->getInfo(),
                    $arguments,
                    $this->scopeContext->getHeader(),
                ));
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
        } finally {
            Workflow::setCurrentContext(null);
        }
    }

    public function getID(): string
    {
        return $this->runId;
    }

    #[Pure]
    public function getWorkflowInstance(): WorkflowInstanceInterface
    {
        return $this->getContext()->getWorkflowInstance();
    }

    protected function complete(mixed $result): void
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
