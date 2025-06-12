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
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\FeatureFlags;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow;
use Temporal\Workflow\HandlerUnfinishedPolicy as HandlerPolicy;
use Temporal\Workflow\ProcessInterface;

/**
 * Root process scope.
 *
 * @implements ProcessInterface<mixed>
 */
class Process extends Scope implements ProcessInterface
{
    public function __construct(
        ServiceContainer $services,
        private readonly string $runId,
        WorkflowInstance $workflowInstance,
    ) {
        parent::__construct($services);

        $inboundPipeline = $services->interceptorProvider->getPipeline(WorkflowInboundCallsInterceptor::class);

        // Configure query handler in an immutable scope
        $workflowInstance->getQueryDispatcher()
            ->setQueryExecutor(function (QueryInput $input, callable $handler) use ($inboundPipeline): mixed {
                try {
                    return $inboundPipeline->with(
                        function (QueryInput $input) use ($handler): mixed {
                            $context = $this->scopeContext
                                ->withInput(new Input($this->scopeContext->getInfo(), $input->arguments));
                            Workflow::setCurrentContext($context);
                            return $handler($input->arguments);
                        },
                        /** @see WorkflowInboundCallsInterceptor::handleQuery() */
                        'handleQuery',
                    )($input);
                } finally {
                    Workflow::setCurrentContext(null);
                }
            });

        // Configure update validator in an immutable scope
        $workflowInstance->getUpdateDispatcher()
            ->setUpdateValidator(function (UpdateInput $input, callable $handler) use ($inboundPipeline): void {
                try {
                    Workflow::setCurrentContext($this->scopeContext);
                    $inboundPipeline->with(
                        function (UpdateInput $input) use ($handler): void {
                            Workflow::setCurrentContext($this->scopeContext->withInput(
                                new Input(
                                    $this->scopeContext->getInfo(),
                                    $input->arguments,
                                    $input->header,
                                ),
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
        $workflowInstance->getUpdateDispatcher()
            ->setUpdateExecutor(function (UpdateInput $input, callable $handler, Deferred $resolver) use ($inboundPipeline): PromiseInterface {
                try {
                    // Define Context for interceptors Pipeline
                    $scope = $this->createScope(
                        detached: true,
                        layer: LoopInterface::ON_TICK,
                        context: $this->context->withInput(
                            new Input($input->info, $input->arguments, $input->header),
                        ),
                        updateContext: Workflow\UpdateContext::fromInput($input),
                    );

                    $scope->startUpdate(
                        static function () use ($handler, $inboundPipeline, $input): mixed {
                            return $inboundPipeline->with(
                                static fn(UpdateInput $input): mixed => $handler($input->arguments),
                                /** @see WorkflowInboundCallsInterceptor::handleUpdate() */
                                'handleUpdate',
                            )($input);
                        },
                        $input,
                        $resolver,
                    );

                    return $scope->promise();
                } finally {
                    Workflow::setCurrentContext(null);
                }
            });

        // Configure signal handler
        $workflowInstance->getSignalDispatcher()->onSignal(
            function (string $name, callable $handler, ValuesInterface $arguments) use ($inboundPipeline): void {
                // Define Context for interceptors Pipeline
                Workflow::setCurrentContext($this->scopeContext);

                $inboundPipeline->with(
                    function (SignalInput $input) use ($handler): void {
                        $this->createScope(
                            true,
                            LoopInterface::ON_SIGNAL,
                            $this->context->withInput(
                                new Input($input->info, $input->arguments, $input->header),
                            ),
                        )->onClose(
                            function (?\Throwable $error): void {
                                if ($error !== null) {
                                    // Fail process when signal scope fails
                                    $this->complete($error);
                                }
                            },
                        )->startSignal(
                            $handler,
                            $input->arguments,
                            $input->signalName,
                        );
                    },
                    /** @see WorkflowInboundCallsInterceptor::handleSignal() */
                    'handleSignal',
                )(new SignalInput(
                    $name,
                    $this->scopeContext->getInfo(),
                    $arguments,
                    $this->scopeContext->getHeader(),
                    $this->scopeContext->isReplaying(),
                ));
            },
        );

        // unlike other scopes Process will notify the server when complete instead of pushing the result
        // to parent scope (there are no parent scope)
        $this->promise()->then(
            function (mixed $result): void {
                $this->complete([$result]);
            },
            function (\Throwable $e): void {
                $this->complete($e);
            },
        );
    }

    /**
     * Initialize workflow instance and start execution.
     */
    public function initAndStart(
        WorkflowContext $context,
        WorkflowInstance $instance,
        bool $deferred,
    ): void {
        $handler = $instance->getHandler();
        $instance = $context->getWorkflowInstance();
        $arguments = null;

        try {
            // Initialize workflow instance
            //
            // Resolve arguments if #[WorkflowInit] is used
            if ($instance->getPrototype()->hasInitializer()) {
                // Resolve args
                $values = $handler->resolveArguments($context->getInput());
                $arguments = EncodedValues::fromValues($values);
                /** @psalm-suppress InaccessibleProperty */
                $context = $context->withInput(
                    new Input(
                        $context->getInfo(),
                        $arguments,
                        $context->getHeader(),
                    ),
                );
                Workflow::setCurrentContext($context);

                $instance->init($values);
            } else {
                Workflow::setCurrentContext($context);
                $instance->init();
            }

            $context->setReadonly(false);

            // Execute
            //
            // Run workflow handler in an interceptor pipeline
            $this->services->interceptorProvider
                ->getPipeline(WorkflowInboundCallsInterceptor::class)
                ->with(
                    function (WorkflowInput $input) use ($context, $arguments, $handler, $deferred): void {
                        // Prepare typed input if values have been changed
                        if ($arguments === null || $input->arguments !== $context->getInput()) {
                            $arguments = EncodedValues::fromValues($handler->resolveArguments($input->arguments));
                        }

                        $context = $context->withInput(new Input($input->info, $input->arguments, $input->header));
                        $this->setContext($context);
                        $this->start($handler, $arguments, $deferred);
                    },
                    /** @see WorkflowInboundCallsInterceptor::execute() */
                    'execute',
                )(new WorkflowInput(
                    $context->getInfo(),
                    $context->getInput(),
                    $context->getHeader(),
                    $context->isReplaying(),
                ));
        } catch (\Throwable $e) {
            /** @psalm-suppress RedundantPropertyInitializationCheck */
            isset($this->context) or $this->setContext($context->setReadonly(false));
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
        return $this->context->getWorkflowInstance();
    }

    protected function complete(mixed $result): void
    {
        if ($result instanceof \Throwable) {
            if ($result instanceof DestructMemorizedInstanceException) {
                // do not handle
                return;
            }

            $this->logRunningHandlers($result instanceof CanceledFailure ? 'cancelled' : 'failed');

            if ($this->services->exceptionInterceptor->isRetryable($result)) {
                $this->scopeContext->panic($result);
                return;
            }

            $this->scopeContext->complete([], $result);
            return;
        }

        if ($this->scopeContext->isContinuedAsNew()) {
            $this->logRunningHandlers('continued as new');
            return;
        }

        $this->logRunningHandlers();
        $this->scopeContext->complete($result);
    }

    /**
     * Log about running handlers on Workflow cancellation, failure, and success.
     */
    private function logRunningHandlers(string $happened = 'finished'): void
    {
        // Skip logging if the feature flag is disabled
        if (!FeatureFlags::$warnOnWorkflowUnfinishedHandlers) {
            return;
        }

        // Skip logging if the workflow is replaying or no handlers are running
        if ($this->context->isReplaying() || !$this->context->getHandlerState()->hasRunningHandlers()) {
            return;
        }

        $prototype = $this->context->getWorkflowInstance()->getPrototype();
        $warnSignals = $warnUpdates = [];

        // Signals
        $definitions = $prototype->getSignalHandlers();
        $signals = $this->context->getHandlerState()->getRunningSignals();
        foreach ($signals as $name => $count) {
            // Check statically defined signals
            if (\array_key_exists($name, $definitions) && $definitions[$name]->policy === HandlerPolicy::Abandon) {
                continue;
            }

            // Dynamically defined signals should be warned
            $warnSignals[] = ['name' => $name, 'count' => $count];
        }

        // Updates
        $definitions = $prototype->getUpdateHandlers();
        $updates = $this->context->getHandlerState()->getRunningUpdates();
        foreach ($updates as $tuple) {
            $name = $tuple['name'];
            // Check statically defined updates
            if (\array_key_exists($name, $definitions) && $definitions[$name]->policy === HandlerPolicy::Abandon) {
                continue;
            }

            // Dynamically defined updates should be warned
            $warnUpdates[] = $tuple;
        }

        $info = $this->context->getInfo();
        $workflowName = $info->type->name;
        $logger = $this->services->logger;

        // Warn messages
        if ($warnUpdates !== []) {
            $message = "Workflow `$workflowName` $happened while update handlers are still running. " .
                'This may have interrupted work that the update handler was doing, and the client ' .
                'that sent the update will receive a \'workflow execution already completed\' RPCError ' .
                'instead of the update result. You can wait for all update and signal handlers ' .
                'to complete by using `yield Workflow::await(Workflow::allHandlersFinished(...));`. ' .
                'Alternatively, if both you and the clients sending the update are okay with interrupting ' .
                'running handlers when the workflow finishes, and causing clients to receive errors, ' .
                'then you can disable this warning via the update handler attribute: ' .
                '`#[UpdateMethod(unfinishedPolicy: HandlerUnfinishedPolicy::Abandon)]`. ' .
                'The following updates were unfinished (and warnings were not disabled for their handler): ' .
                \implode(', ', \array_map(static fn(array $v): string => "`$v[name]` id:$v[id]", $warnUpdates));

            $logger->warning($message, [
                'workflow_type' => $workflowName,
                'workflow_id' => $info->execution->getID(),
                'run_id' => $info->execution->getRunID(),
            ]);
        }

        if ($warnSignals !== []) {
            $message = "Workflow `$workflowName` $happened while signal handlers are still running. " .
                'This may have interrupted work that the signal handler was doing. ' .
                'You can wait for all update and signal handlers to complete by using ' .
                '`yield Workflow::await(Workflow::allHandlersFinished(...));`. ' .
                'Alternatively, if both you and the clients sending the signal are okay ' .
                'with interrupting running handlers when the workflow finishes, ' .
                'and causing clients to receive errors, then you can disable this warning via the signal ' .
                'handler attribute: `#[SignalMethod(unfinishedPolicy: HandlerUnfinishedPolicy::Abandon)]`. ' .
                'The following signals were unfinished (and warnings were not disabled for their handler): ' .
                \implode(', ', \array_map(static fn(array $v): string => "`$v[name]` x$v[count]", $warnSignals));

            $logger->warning($message, [
                'workflow_type' => $workflowName,
                'workflow_id' => $info->execution->getID(),
                'run_id' => $info->execution->getRunID(),
            ]);
        }
    }
}
