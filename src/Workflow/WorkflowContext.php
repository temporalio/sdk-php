<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Carbon\CarbonInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Support\StackRenderer;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\CompletableResultInterface;
use Temporal\Internal\Transport\Request\ContinueAsNew;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Internal\Transport\Request\SideEffect;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Internal\Workflow\ActivityStub;
use Temporal\Internal\Workflow\ChildWorkflowProxy;
use Temporal\Internal\Workflow\ChildWorkflowStub;
use Temporal\Internal\Workflow\ContinueAsNewProxy;
use Temporal\Internal\Workflow\Input;
use Temporal\Promise;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\RequestInterface;

use function React\Promise\reject;

class WorkflowContext implements WorkflowContextInterface
{
    protected ServiceContainer $services;
    protected ClientInterface $client;

    private array $awaits = [];

    protected Input $input;
    protected WorkflowInstanceInterface $workflowInstance;
    protected ?ValuesInterface $lastCompletionResult = null;

    private array $trace = [];
    private bool $continueAsNew = false;

    /**
     * WorkflowContext constructor.
     * @param ServiceContainer $services
     * @param ClientInterface $client
     * @param WorkflowInstanceInterface $workflowInstance
     * @param Input $input
     * @param ValuesInterface|null $lastCompletionResult
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
    }

    /**
     * @return WorkflowInstanceInterface
     */
    public function getWorkflowInstance(): WorkflowInstanceInterface
    {
        return $this->workflowInstance;
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

    /**
     * {@inheritDoc}
     */
    public function now(): CarbonInterface
    {
        return $this->services->env->now();
    }

    /**
     * @return string
     */
    public function getRunId(): string
    {
        return $this->input->info->execution->runId;
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
     * @param Type|string $type
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
     * @return DataConverterInterface
     */
    public function getDataConverter(): DataConverterInterface
    {
        return $this->services->dataConverter;
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
        return $this->request(
            new GetVersion($changeId, $minSupported, $maxSupported)
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

        return $this->services->client->request(
            new CompleteWorkflow($values, $failure)
        );
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
        return $this->services->client->request($request);
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
        \ReflectionType $returnType = null
    ): PromiseInterface {
        return $this->newUntypedChildWorkflowStub($type, $options)->execute($args, $returnType);
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedChildWorkflowStub(
        string $name,
        ChildWorkflowOptions $options = null
    ): ChildWorkflowStubInterface {
        $options ??= new ChildWorkflowOptions();

        return new ChildWorkflowStub($this->services->marshaller, $name, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null): object
    {
        $workflow = $this->services->workflowsReader->fromClass($class);

        return new ChildWorkflowProxy(
            $class,
            $workflow,
            $options ?? new ChildWorkflowOptions(),
            $this
        );
    }

    /**
     * {@inheritDoc}
     */
    public function executeActivity(
        string $type,
        array $args = [],
        ActivityOptions $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface {
        return $this->newUntypedActivityStub($options)->execute($type, $args, $returnType);
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedActivityStub(ActivityOptions $options = null): ActivityStubInterface
    {
        $options ??= new ActivityOptions();

        return new ActivityStub($this->services->marshaller, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(string $class, ActivityOptions $options = null): object
    {
        $activities = $this->services->activitiesReader->fromClass($class);

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
        return $this->request(
            new NewTimer(DateInterval::parse($interval, DateInterval::FORMAT_SECONDS))
        );
    }

    /**
     * {@inheritDoc}
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        $this->recordTrace();
        return $this->client->request($request);
    }

    /**
     * {@inheritDoc}
     */
    public function getLastTrace(): string
    {
        return StackRenderer::renderTrace($this->trace);
    }

    /**
     * @param mixed ...$condition
     * @return PromiseInterface
     */
    public function await(...$condition): PromiseInterface
    {
        $conditions = [];
        foreach ($condition as $cond) {
            if ($cond instanceof PromiseInterface) {
                $conditions[] = $cond;
                continue;
            }

            $conditions[] = $this->addCondition($cond);
        }

        if (count($conditions) === 1) {
            return $conditions[0];
        }

        return Promise::any($conditions);
    }

    /**
     * @param callable $condition
     * @return PromiseInterface
     */
    public function addCondition(callable $condition): PromiseInterface
    {
        $deferred = new Deferred();
        $this->awaits[] = [$condition, $deferred];
        return $deferred->promise();
    }

    /**
     * Calculate unblocked conditions.
     */
    public function resolveConditions()
    {
        foreach ($this->awaits as $i => $cond) {
            [$condition, $deferred] = $cond;
            if ($condition()) {
                unset($this->awaits[$i]);
                $deferred->resolve();
            }
        }
    }
}
