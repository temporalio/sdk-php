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
use RuntimeException;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Activity\LocalActivityOptions;
use Temporal\Common\Uuid;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
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
use Temporal\Promise;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\ActivityStubInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\ContinueAsNewOptions;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInfo;
use Temporal\Internal\Transport\Request\UpsertSearchAttributes;

use function React\Promise\reject;
use function React\Promise\resolve;

class WorkflowContext implements WorkflowContextInterface
{
    protected ServiceContainer $services;
    protected ClientInterface $client;

    protected Input $input;
    protected WorkflowInstanceInterface $workflowInstance;
    protected ?ValuesInterface $lastCompletionResult = null;

    protected array $awaits = [];
    protected array $asyncAwaits = [];
    /**
     * @var <CompletableResultInterface, CommandInterface>
     */
    protected \SplObjectStorage $timers;

    private array $trace = [];
    private bool $continueAsNew = false;

    /**
     * WorkflowContext constructor.
     * @param ServiceContainer          $services
     * @param ClientInterface           $client
     * @param WorkflowInstanceInterface $workflowInstance
     * @param Input                     $input
     * @param ValuesInterface|null      $lastCompletionResult
     */
    public function __construct(
        ServiceContainer $services,
        ClientInterface $client,
        WorkflowInstanceInterface $workflowInstance,
        Input $input,
        ?ValuesInterface $lastCompletionResult
    ) {
        $this->services = $services;
        $this->client = $client;
        $this->workflowInstance = $workflowInstance;
        $this->input = $input;
        $this->lastCompletionResult = $lastCompletionResult;
        $this->timers = new \SplObjectStorage();
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
     * {@inheritDoc}
     */
    public function getInput(): ValuesInterface
    {
        return $this->input->input;
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
        return EncodedValues::decodePromise(
            $this->request(new GetVersion($changeId, $minSupported, $maxSupported)),
            Type::TYPE_ANY
        );
    }

    /**
     * {@inheritDoc}
     */
    public function sideEffect(callable $context): PromiseInterface
    {
        try {
            $value = $this->isReplaying() ? null : $context();
        } catch (\Throwable $e) {
            return reject($e);
        }

        $returnType = null;
        try {
            $reflection = new \ReflectionFunction($context);
            $returnType = $reflection->getReturnType();
        } catch (\Throwable $e) {
        }

        return EncodedValues::decodePromise(
            $this->request(new SideEffect(EncodedValues::fromValues([$value]))),
            $returnType
        );
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
        if ($result !== null) {
            $values = EncodedValues::fromValues($result);
        } else {
            $values = EncodedValues::empty();
        }

        return $this->request(new CompleteWorkflow($values, $failure), false);
    }

    /**
     * {@inheritDoc}
     */
    public function panic(\Throwable $failure = null): PromiseInterface
    {
        return $this->request(new Panic($failure), false);
    }

    /**
     * {@inheritDoc}
     */
    public function continueAsNew(
        string $type,
        array $args = [],
        ContinueAsNewOptions $options = null
    ): PromiseInterface {
        $this->continueAsNew = true;

        $request = new ContinueAsNew(
            $type,
            EncodedValues::fromValues($args),
            $this->services->marshaller->marshal($options ?? new ContinueAsNewOptions())
        );

        // must not be captured
        return $this->request($request, false);
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
        $returnType = null
    ): PromiseInterface {
        return $this->newUntypedChildWorkflowStub($type, $options)
            ->execute($args, $returnType);
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedChildWorkflowStub(
        string $type,
        ChildWorkflowOptions $options = null
    ): ChildWorkflowStubInterface {
        $options ??= (new ChildWorkflowOptions())->withNamespace($this->getInfo()->namespace);

        return new ChildWorkflowStub($this->services->marshaller, $type, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null): object
    {
        $workflow = $this->services->workflowsReader->fromClass($class);
        $options = $options ?? (new ChildWorkflowOptions())->withNamespace($this->getInfo()->namespace);

        return new ChildWorkflowProxy(
            $class,
            $workflow,
            $options,
            $this
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
        return new ExternalWorkflowStub($execution);
    }

    /**
     * {@inheritDoc}
     */
    public function executeActivity(
        string $type,
        array $args = [],
        ActivityOptionsInterface $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface {
        return $this->newUntypedActivityStub($options)->execute($type, $args, $returnType);
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedActivityStub(ActivityOptionsInterface $options = null): ActivityStubInterface
    {
        $options ??= new ActivityOptions();

        return new ActivityStub($this->services->marshaller, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(string $class, ActivityOptionsInterface $options = null): object
    {
        $activities = $this->services->activitiesReader->fromClass($class);

        if (isset($activities[0]) && $activities[0]->isLocalActivity() && !$options instanceof LocalActivityOptions) {
            throw new RuntimeException("Local activity can be used only with LocalActivityOptions");
        }

        return new ActivityProxy(
            $class,
            $activities,
            $options ?? ActivityOptions::new(),
            $this
        );
    }

    /**
     * {@inheritDoc}
     */
    public function timer($interval): PromiseInterface
    {
        $request = new NewTimer(DateInterval::parse($interval, DateInterval::FORMAT_SECONDS));
        $result = $this->request($request);
        $this->timers->attach($result, $request);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function request(RequestInterface $request, bool $cancellable = true): PromiseInterface
    {
        $this->recordTrace();
        return $this->client->request($request);
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
        $this->services->client->request(
            new UpsertSearchAttributes($searchAttributes)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function await(...$conditions): PromiseInterface
    {
        $result = [];
        $conditionGroupId = Uuid::v4();

        foreach ($conditions as $condition) {
            assert(\is_callable($condition) || $condition instanceof PromiseInterface);

            if ($condition instanceof \Closure) {
                $callableResult = $condition($conditionGroupId);
                if ($callableResult === true) {
                    $this->resolveConditionGroup($conditionGroupId);
                    return resolve(true);
                }
                $result[] = $this->addCondition($conditionGroupId, $condition);
            }

            if ($condition instanceof PromiseInterface)
            {
                $result[] = $this->addAsyncCondition($conditionGroupId, $condition);
            }
        }

        if (\count($result) === 1) {
            return $result[0];
        }

        return Promise::any($result);
    }

    /**
     * {@inheritDoc}
     */
    public function awaitWithTimeout($interval, ...$conditions): PromiseInterface
    {
        $timer = $this->timer($interval);

        $conditions[] = $timer;

        return $this->await(...$conditions)
            ->then(static fn (): bool => !$timer->isComplete());
    }

    /**
     * Calculate unblocked conditions.
     */
    public function resolveConditions(): void
    {
        foreach ($this->awaits as $awaitsGroupId => $awaitsGroup) {
            foreach ($awaitsGroup as $i => $cond) {
                [$condition, $deferred] = $cond;
                if ($condition()) {
                    $deferred->resolve();
                    unset($this->awaits[$awaitsGroupId][$i]);
                    $this->resolveConditionGroup($awaitsGroupId);
                }
            }
        }
    }

    /**
     * @param string $conditionGroupId
     * @param callable $condition
     * @return PromiseInterface
     */
    protected function addCondition(string $conditionGroupId, callable $condition): PromiseInterface
    {
        $deferred = new Deferred();
        $this->awaits[$conditionGroupId][] = [$condition, $deferred];

        return $deferred->promise();
    }

    protected function addAsyncCondition(string $conditionGroupId, PromiseInterface $condition): PromiseInterface
    {
        $this->asyncAwaits[$conditionGroupId][] = $condition;
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
     * Record last stack trace of the call.
     *
     * @return void
     */
    protected function recordTrace(): void
    {
        $this->trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    public function resolveConditionGroup(string $conditionGroupId): void
    {
        // First resolve pending promises
        if (isset($this->awaits[$conditionGroupId])) {
            foreach ($this->awaits[$conditionGroupId] as $i => $cond) {
                [$_, $deferred] = $cond;
                unset($this->awaits[$conditionGroupId][$i]);
                $deferred->resolve();
            }
            unset($this->awaits[$conditionGroupId]);
        }

        $this->clearAsyncAwaits($conditionGroupId);
    }

    public function rejectConditionGroup(string $conditionGroupId): void
    {
        if (isset($this->awaits[$conditionGroupId])) {
            foreach ($this->awaits[$conditionGroupId] as $i => $cond) {
                [$_, $deferred] = $cond;
                unset($this->awaits[$conditionGroupId][$i]);
                $deferred->reject();
            }
            unset($this->awaits[$conditionGroupId]);
        }

        $this->clearAsyncAwaits($conditionGroupId);
    }

    private function clearAsyncAwaits(string $conditionGroupId): void
    {
        // Check pending timers in this group
        if (!isset($this->asyncAwaits[$conditionGroupId])) {
            return;
        }

        // Then cancel any pending timers if exist
        foreach ($this->asyncAwaits[$conditionGroupId] as $index => $awaitCondition) {
            if (!$awaitCondition->isComplete()) {
                /** @var NewTimer $timer */
                $timer = $this->timers->offsetGet($awaitCondition);
                if ($timer !== null) {
                    $request = new Cancel($timer->getID());
                    $this->request($request);
                    $this->timers->offsetUnset($awaitCondition);
                }
            }
            unset($this->asyncAwaits[$conditionGroupId][$index]);
        }
        unset($this->asyncAwaits[$conditionGroupId]);
    }
}
