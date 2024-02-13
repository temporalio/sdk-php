<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Activity\LocalActivityOptions;
use Temporal\Common\Uuid;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Interceptor\WorkflowOutboundCalls\AwaitInput;
use Temporal\Interceptor\WorkflowOutboundCalls\AwaitWithTimeoutInput;
use Temporal\Interceptor\WorkflowOutboundCalls\CompleteInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ContinueAsNewInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteActivityInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteChildWorkflowInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteLocalActivityInput;
use Temporal\Interceptor\WorkflowOutboundCalls\GetVersionInput;
use Temporal\Interceptor\WorkflowOutboundCalls\PanicInput;
use Temporal\Interceptor\WorkflowOutboundCalls\SideEffectInput;
use Temporal\Interceptor\WorkflowOutboundCalls\TimerInput;
use Temporal\Interceptor\WorkflowOutboundCalls\UpsertSearchAttributesInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Internal\Declaration\Destroyable;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Interceptor\HeaderCarrier;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\StackRenderer;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\CompletableResultInterface;
use Temporal\Internal\Transport\Request\Cancel;
use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Internal\Transport\Request\ContinueAsNew;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Internal\Transport\Request\Panic;
use Temporal\Internal\Transport\Request\SideEffect;
use Temporal\Internal\Transport\Request\UpsertSearchAttributes;
use Temporal\Promise;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\ActivityStubInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\ContinueAsNewOptions;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInfo;

use function React\Promise\reject;
use function React\Promise\resolve;

class WorkflowContext implements WorkflowContextInterface, HeaderCarrier, Destroyable
{
    /**
     * Contains conditional groups that contains tuple of a condition callable and its promise
     * @var array<non-empty-string, array<int<0, max>, array{callable, Deferred}>>
     */
    protected array $awaits = [];

    private array $trace = [];
    private bool $continueAsNew = false;

    /** @var Pipeline<WorkflowOutboundRequestInterceptor, PromiseInterface> */
    private Pipeline $requestInterceptor;

    /** @var Pipeline<WorkflowOutboundCallsInterceptor, PromiseInterface> */
    private Pipeline $callsInterceptor;

    public function __construct(
        protected ServiceContainer $services,
        protected ClientInterface $client,
        protected WorkflowInstanceInterface&Destroyable $workflowInstance,
        protected Input $input,
        protected ?ValuesInterface $lastCompletionResult = null
    ) {
        $this->requestInterceptor =  $services->interceptorProvider
            ->getPipeline(WorkflowOutboundRequestInterceptor::class);
        $this->callsInterceptor =  $services->interceptorProvider
            ->getPipeline(WorkflowOutboundCallsInterceptor::class);
    }

    /**
     * @return WorkflowInstanceInterface
     */
    public function getWorkflowInstance(): WorkflowInstanceInterface
    {
        return $this->workflowInstance;
    }

    /**
     * {@inheritDoc}
     */
    public function now(): \DateTimeInterface
    {
        return $this->services->env->now();
    }

    /**
     * @return string
     */
    public function getRunId(): string
    {
        return $this->input->info->execution->getRunID();
    }

    /**
     * {@inheritDoc}
     */
    public function getInfo(): WorkflowInfo
    {
        return $this->input->info;
    }

    /**
     * @inheritDoc
     */
    public function getHeader(): HeaderInterface
    {
        return $this->input->header;
    }

    /**
     * {@inheritDoc}
     */
    public function getInput(): ValuesInterface
    {
        return $this->input->input;
    }

    public function withInput(Input $input): static
    {
        $clone = clone $this;
        $clone->awaits = &$this->awaits;
        $clone->trace = &$this->trace;
        $clone->input = $input;
        return $clone;
    }

    /**
     * @return ValuesInterface|null
     */
    public function getLastCompletionResultValues(): ?ValuesInterface
    {
        return $this->lastCompletionResult;
    }

    /**
     * Get value of last completion result, if any.
     *
     * @param Type|string|null $type
     * @return mixed
     */
    public function getLastCompletionResult($type = null)
    {
        if ($this->lastCompletionResult === null) {
            return null;
        }

        return $this->lastCompletionResult->getValue(0, $type);
    }

    /**
     * @return ClientInterface
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * {@inheritDoc}
     */
    public function registerQuery(string $queryType, callable $handler): WorkflowContextInterface
    {
        $this->getWorkflowInstance()->addQueryHandler($queryType, $handler);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function registerSignal(string $queryType, callable $handler): WorkflowContextInterface
    {
        $this->getWorkflowInstance()->addSignalHandler($queryType, $handler);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        return $this->callsInterceptor->with(
            fn(GetVersionInput $input): PromiseInterface => EncodedValues::decodePromise(
                $this->request(new GetVersion($input->changeId, $input->minSupported, $input->maxSupported)),
                Type::TYPE_ANY,
            ),
            /** @see WorkflowOutboundCallsInterceptor::getVersion() */
            'getVersion',
        )(new GetVersionInput($changeId, $minSupported, $maxSupported));
    }

    /**
     * {@inheritDoc}
     */
    public function sideEffect(callable $context): PromiseInterface
    {
        $value = null;
        $closure = \Closure::fromCallable($context);

        try {
            if (!$this->isReplaying()) {
                $value = $this->callsInterceptor->with(
                    $closure,
                    /** @see WorkflowOutboundCallsInterceptor::sideEffect() */
                    'sideEffect',
                )(new SideEffectInput($closure));
            }
        } catch (\Throwable $e) {
            return reject($e);
        }

        $returnType = null;
        try {
            $reflection = new \ReflectionFunction($closure);
            $returnType = $reflection->getReturnType();
        } catch (\Throwable) {
        }

        $last = fn() => EncodedValues::decodePromise(
            $this->request(new SideEffect(EncodedValues::fromValues([$value]))),
            $returnType,
        );
        return $last();
    }

    /**
     * {@inheritDoc}
     */
    public function isReplaying(): bool
    {
        return $this->services->env->isReplaying();
    }

    /**
     * {@inheritDoc}
     */
    public function complete(array $result = null, \Throwable $failure = null): PromiseInterface
    {
        if ($failure !== null) {
            $this->workflowInstance->clearSignalQueue();
        }

        return $this->callsInterceptor->with(
            function (CompleteInput $input): PromiseInterface {
                $values = $input->result !== null
                    ? EncodedValues::fromValues($input->result)
                    : EncodedValues::empty();

                return $this->request(new CompleteWorkflow($values, $input->failure), false);
            },
            /** @see WorkflowOutboundCallsInterceptor::complete() */
            'complete',
        )(new CompleteInput($result, $failure));
    }

    /**
     * {@inheritDoc}
     */
    public function panic(\Throwable $failure = null): PromiseInterface
    {
        return $this->callsInterceptor->with(
            fn(PanicInput $failure): PromiseInterface => $this->request(new Panic($failure->failure), false),
            /** @see WorkflowOutboundCallsInterceptor::panic() */
            'panic',
        )(new PanicInput($failure));
    }

    /**
     * {@inheritDoc}
     */
    public function continueAsNew(
        string $type,
        array $args = [],
        ContinueAsNewOptions $options = null
    ): PromiseInterface {
        return $this->callsInterceptor->with(
            function (ContinueAsNewInput $input): PromiseInterface {
                $this->continueAsNew = true;

                $request = new ContinueAsNew(
                    $input->type,
                    EncodedValues::fromValues($input->args),
                    $this->services->marshaller->marshal($input->options ?? new ContinueAsNewOptions()),
                    $this->getHeader(),
                );

                // must not be captured
                return $this->request($request, false);
            },
            /** @see WorkflowOutboundCallsInterceptor::continueAsNew() */
            'continueAsNew',
        )(new ContinueAsNewInput($type, $args, $options));
    }

    /**
     * {@inheritDoc}
     */
    public function newContinueAsNewStub(string $class, ContinueAsNewOptions $options = null): object
    {
        $options ??= new ContinueAsNewOptions();

        $workflow = $this->services->workflowsReader->fromClass($class);

        return new ContinueAsNewProxy($class, $workflow, $options, $this);
    }

    /**
     * @return bool
     */
    public function isContinuedAsNew(): bool
    {
        return $this->continueAsNew;
    }

    /**
     * {@inheritDoc}
     */
    public function executeChildWorkflow(
        string $type,
        array $args = [],
        ChildWorkflowOptions $options = null,
        mixed $returnType = null,
    ): PromiseInterface {
        return $this->callsInterceptor->with(
            fn(ExecuteChildWorkflowInput $input): PromiseInterface => $this
                ->newUntypedChildWorkflowStub($input->type, $input->options)
                ->execute($input->args, $input->returnType),
            /** @see WorkflowOutboundCallsInterceptor::executeChildWorkflow() */
            'executeChildWorkflow',
        )(new ExecuteChildWorkflowInput($type, $args, $options, $returnType));
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedChildWorkflowStub(
        string $type,
        ChildWorkflowOptions $options = null,
    ): ChildWorkflowStubInterface {
        $options ??= (new ChildWorkflowOptions())
            ->withNamespace($this->getInfo()->namespace);

        return new ChildWorkflowStub($this->services->marshaller, $type, $options, $this->getHeader());
    }

    /**
     * {@inheritDoc}
     */
    public function newChildWorkflowStub(
        string $class,
        ChildWorkflowOptions $options = null,
    ): object {
        $workflow = $this->services->workflowsReader->fromClass($class);
        $options = $options ?? (new ChildWorkflowOptions())
            ->withNamespace($this->getInfo()->namespace);

        return new ChildWorkflowProxy(
            $class,
            $workflow,
            $options,
            $this,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newExternalWorkflowStub(string $class, WorkflowExecution $execution): object
    {
        $workflow = $this->services->workflowsReader->fromClass($class);

        $stub = $this->newUntypedExternalWorkflowStub($execution);

        return new ExternalWorkflowProxy($class, $workflow, $stub);
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedExternalWorkflowStub(WorkflowExecution $execution): ExternalWorkflowStubInterface
    {
        return new ExternalWorkflowStub($execution, $this->callsInterceptor);
    }

    /**
     * {@inheritDoc}
     */
    public function executeActivity(
        string $type,
        array $args = [],
        ActivityOptionsInterface $options = null,
        Type|string|\ReflectionClass|\ReflectionType $returnType = null,
    ): PromiseInterface {
        $isLocal = $options instanceof LocalActivityOptions;

        return $isLocal
            ? $this->callsInterceptor->with(
                fn(ExecuteLocalActivityInput $input): PromiseInterface => $this
                    ->newUntypedActivityStub($input->options)
                    ->execute($input->type, $input->args, $input->returnType, true),
                /** @see WorkflowOutboundCallsInterceptor::executeLocalActivity() */
                'executeLocalActivity',
            )(new ExecuteLocalActivityInput($type, $args, $options, $returnType))
            : $this->callsInterceptor->with(
                fn(ExecuteActivityInput $input): PromiseInterface => $this
                    ->newUntypedActivityStub($input->options)
                    ->execute($input->type, $input->args, $input->returnType),
                /** @see WorkflowOutboundCallsInterceptor::executeActivity() */
                'executeActivity',
            )(new ExecuteActivityInput($type, $args, $options, $returnType));
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedActivityStub(
        ActivityOptionsInterface $options = null,
    ): ActivityStubInterface {
        $options ??= new ActivityOptions();

        return new ActivityStub($this->services->marshaller, $options, $this->getHeader());
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(
        string $class,
        ActivityOptionsInterface $options = null,
    ): ActivityProxy {
        $activities = $this->services->activitiesReader->fromClass($class);

        if (isset($activities[0]) && $activities[0]->isLocalActivity() && !$options instanceof LocalActivityOptions) {
            throw new RuntimeException("Local activity can be used only with LocalActivityOptions");
        }

        return new ActivityProxy(
            $class,
            $activities,
            $options ?? ActivityOptions::new(),
            $this,
            $this->callsInterceptor,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function timer($interval): PromiseInterface
    {
        $dateInterval = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);

        return $this->callsInterceptor->with(
            fn(TimerInput $input): PromiseInterface => $this->request(new NewTimer($input->interval)),
            /** @see WorkflowOutboundCallsInterceptor::timer() */
            'timer',
        )(new TimerInput($dateInterval));
    }

    /**
     * {@inheritDoc}
     */
    public function request(RequestInterface $request, bool $cancellable = true): PromiseInterface
    {
        $this->recordTrace();

        // Intercept workflow outbound calls
        return $this->requestInterceptor->with(
            function (RequestInterface $request): PromiseInterface {
                return $this->client->request($request, $this);
            },
            /** @see WorkflowOutboundRequestInterceptor::handleOutboundRequest() */
            'handleOutboundRequest',
        )($request);
    }

    /**
     * {@inheritDoc}
     */
    public function getStackTrace(): string
    {
        return StackRenderer::renderTrace($this->trace);
    }

    /**
     * {@inheritDoc}
     */
    public function upsertSearchAttributes(array $searchAttributes): void
    {
        $this->callsInterceptor->with(
            fn(UpsertSearchAttributesInput $input): PromiseInterface
                => $this->request(new UpsertSearchAttributes($input->searchAttributes)),
            /** @see WorkflowOutboundCallsInterceptor::upsertSearchAttributes() */
            'upsertSearchAttributes',
        )(new UpsertSearchAttributesInput($searchAttributes));
    }

    /**
     * {@inheritDoc}
     */
    public function await(...$conditions): PromiseInterface
    {
        return $this->callsInterceptor->with(
            fn(AwaitInput $input): PromiseInterface => $this->awaitRequest(...$input->conditions),
            /** @see WorkflowOutboundCallsInterceptor::await() */
            'await',
        )(new AwaitInput($conditions));
    }

    /**
     * {@inheritDoc}
     */
    public function awaitWithTimeout($interval, ...$conditions): PromiseInterface
    {
        $intervalObject = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);

        return $this->callsInterceptor->with(
            function (AwaitWithTimeoutInput $input): PromiseInterface {
                /** Bypassing {@see timer()} to acquire a timer request ID */
                $request = new NewTimer($input->interval);
                $requestId = $request->getID();
                $timer = $this->request($request);
                \assert($timer instanceof CompletableResultInterface);

                return $this->awaitRequest($timer, ...$input->conditions)
                    ->then(function () use ($timer, $requestId): bool {
                        $isCompleted = $timer->isComplete();
                        if (!$isCompleted) {
                            // If internal timer was not completed then cancel it
                            $this->request(new Cancel($requestId));
                        }
                        return !$isCompleted;
                    });
            },
            /** @see WorkflowOutboundCallsInterceptor::awaitWithTimeout() */
            'awaitWithTimeout',
        )(new AwaitWithTimeoutInput($intervalObject, $conditions));
    }

    /**
     * Calculate unblocked conditions.
     */
    public function resolveConditions(): void
    {
        foreach ($this->awaits as $awaitsGroupId => $awaitsGroup) {
            foreach ($awaitsGroup as $i => [$condition, $deferred]) {
                if ($condition()) {
                    unset($this->awaits[$awaitsGroupId][$i]);
                    $deferred->resolve();
                    $this->resolveConditionGroup($awaitsGroupId);
                }
            }
        }
    }

    public function resolveConditionGroup(string $conditionGroupId): void
    {
        unset($this->awaits[$conditionGroupId]);
    }

    public function rejectConditionGroup(string $conditionGroupId): void
    {
        unset($this->awaits[$conditionGroupId]);
    }

    /**
     * {@inheritDoc}
     */
    public function uuid(): PromiseInterface
    {
        return $this->sideEffect(static fn(): UuidInterface => \Ramsey\Uuid\Uuid::uuid4());
    }

    /**
     * {@inheritDoc}
     */
    public function uuid4(): PromiseInterface
    {
        return $this->sideEffect(static fn(): UuidInterface => \Ramsey\Uuid\Uuid::uuid4());
    }

    /**
     * {@inheritDoc}
     */
    public function uuid7(?DateTimeInterface $dateTime = null): PromiseInterface
    {
        return $this->sideEffect(static fn(): UuidInterface => \Ramsey\Uuid\Uuid::uuid7($dateTime));
    }

    /**
     * @param callable|PromiseInterface ...$conditions
     */
    protected function awaitRequest(...$conditions): PromiseInterface
    {
        $result = [];
        $conditionGroupId = Uuid::v4();

        foreach ($conditions as $condition) {
            \assert(\is_callable($condition) || $condition instanceof PromiseInterface);

            if ($condition instanceof \Closure) {
                $callableResult = $condition($conditionGroupId);
                if ($callableResult === true) {
                    $this->resolveConditionGroup($conditionGroupId);
                    return resolve(true);
                }
                $result[] = $this->addCondition($conditionGroupId, $condition);
                continue;
            }

            if ($condition instanceof PromiseInterface) {
                $result[] = $condition;
            }
        }

        if (\count($result) === 1) {
            return $result[0];
        }

        return Promise::any($result)->then(
            function ($result) use ($conditionGroupId) {
                $this->resolveConditionGroup($conditionGroupId);
                return $result;
            },
            function ($reason) use ($conditionGroupId) {
                $this->rejectConditionGroup($conditionGroupId);
                // Throw the first reason
                // It need to avoid memory leak when the related workflow is destroyed
                if (\is_iterable($reason)) {
                    foreach ($reason as $exception) {
                        if ($exception instanceof \Throwable) {
                            throw $exception;
                        }
                    }
                }
            },
        );
    }

    /**
     * @param non-empty-string $conditionGroupId
     * @param callable $condition
     * @return PromiseInterface
     */
    protected function addCondition(string $conditionGroupId, callable $condition): PromiseInterface
    {
        $deferred = new Deferred();
        $this->awaits[$conditionGroupId][] = [$condition, $deferred];

        return $deferred->promise();
    }

    /**
     * Record last stack trace of the call.
     *
     * @return void
     */
    protected function recordTrace(): void
    {
        $this->trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    public function destroy(): void
    {
        $this->awaits = [];
        $this->workflowInstance->destroy();
        unset($this->workflowInstance);
    }
}
