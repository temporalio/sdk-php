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
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\FeatureFlags;
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
        WorkflowContext $ctx,
        private readonly string $runId,
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
                    ),
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
        $wfInstance->setUpdateExecutor(function (UpdateInput $input, callable $handler, Deferred $resolver) use ($inboundPipeline): PromiseInterface {
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
        $wfInstance->getSignalQueue()->onSignal(
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
                ));
            },
        );

        // unlike other scopes Process will notify the server when complete instead of pushing the result
        // to parent scope (there are no parent scope)
        $this->promise()->then(
            function ($result): void {
                $this->complete([$result]);
            },
            function (\Throwable $e): void {
                $this->complete($e);
            },
        );
    }

    /**
     * @param \Closure(ValuesInterface): mixed $handler
     */
    public function start(\Closure $handler, ValuesInterface $values = null, bool $deferred): void
    {
        try {
            $this->makeCurrent();
            $this->context->getWorkflowInstance()->initConstructor();
            parent::start($handler, $values, $deferred);
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
        if ($this->getContext()->isReplaying() || !$this->getContext()->getHandlerState()->hasRunningHandlers()) {
            return;
        }

        $prototype = $this->getContext()->getWorkflowInstance()->getPrototype();
        $warnSignals = $warnUpdates = [];

        // Signals
        $definitions = $prototype->getSignalHandlers();
        $signals = $this->getContext()->getHandlerState()->getRunningSignals();
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
        $updates = $this->getContext()->getHandlerState()->getRunningUpdates();
        foreach ($updates as $tuple) {
            $name = $tuple['name'];
            // Check statically defined updates
            if (\array_key_exists($name, $definitions) && $definitions[$name]->policy === HandlerPolicy::Abandon) {
                continue;
            }

            // Dynamically defined updates should be warned
            $warnUpdates[] = $tuple;
        }

        $workflowName = $this->getContext()->getInfo()->type->name;

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

            \error_log($message);
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

            \error_log($message);
        }
    }
}
