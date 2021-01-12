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
use Carbon\CarbonTimeZone;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Transport\ClientInterface;
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
use Temporal\Internal\Workflow\Input;
use Temporal\Worker\Transport\Command\RequestInterface;

use function React\Promise\reject;

class WorkflowContext implements WorkflowContextInterface
{
    protected ServiceContainer $services;
    protected ClientInterface $client;

    protected Input $input;
    protected WorkflowInstanceInterface $workflowInstance;

    private array $trace = [];
    private bool $continueAsNew = false;

    /**
     * @param ServiceContainer $services
     * @param ClientInterface $client
     * @param WorkflowInstanceInterface $workflowInstance
     * @param Input $input
     */
    public function __construct(
        ServiceContainer $services,
        ClientInterface $client,
        WorkflowInstanceInterface $workflowInstance,
        Input $input
    ) {
        $this->services = $services;
        $this->client = $client;
        $this->workflowInstance = $workflowInstance;
        $this->input = $input;
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
        $this->recordTrace();

        return $this->services->env->now();
    }

    /**
     * @return string
     */
    public function getRunId(): string
    {
        $this->recordTrace();

        return $this->input->info->execution->runId;
    }

    /**
     * {@inheritDoc}
     */
    public function getInfo(): WorkflowInfo
    {
        $this->recordTrace();

        return $this->input->info;
    }

    /**
     * {@inheritDoc}
     */
    public function getInput(): ValuesInterface
    {
        $this->recordTrace();

        return $this->input->input;
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
        $this->recordTrace();
        $this->getWorkflowInstance()->addQueryHandler($queryType, $handler);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function registerSignal(string $queryType, callable $handler): WorkflowContextInterface
    {
        $this->recordTrace();
        $this->getWorkflowInstance()->addSignalHandler($queryType, $handler);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        $this->recordTrace();

        // todo: what is payload?
        return $this->request(
            new GetVersion($changeId, $minSupported, $maxSupported)
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
    public function sideEffect(callable $context): PromiseInterface
    {
        $this->recordTrace();

        try {
            $value = $this->isReplaying() ? null : $context();
        } catch (\Throwable $e) {
            return reject($e);
        }

        // todo: get return type from context (is it possible?)
        return EncodedValues::decodePromise(
            $this->request(
                new SideEffect(EncodedValues::fromValues([$value]))
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isReplaying(): bool
    {
        $this->recordTrace();

        return $this->services->env->isReplaying();
    }

    /**
     * {@inheritDoc}
     */
    public function complete(array $result = null, \Throwable $failure = null): PromiseInterface
    {
        $this->recordTrace();

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
    public function continueAsNew(string $name, ...$input): PromiseInterface
    {
        $this->recordTrace();
        $this->continueAsNew = true;

        // must not be captured
        return $this->services->client->request(new ContinueAsNew($name, EncodedValues::fromValues($input)));
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
        $this->recordTrace();

        return $this->newUntypedChildWorkflowStub($type, $options)->execute($args, $returnType);
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedChildWorkflowStub(
        string $name,
        ChildWorkflowOptions $options = null
    ): ChildWorkflowStubInterface {
        $this->recordTrace();
        $options ??= new ChildWorkflowOptions();

        return new ChildWorkflowStub($this->services->marshaller, $name, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null): object
    {
        $this->recordTrace();
        $workflows = $this->services->workflowsReader->fromClass($class);

        return new ChildWorkflowProxy(
            $class,
            $workflows,
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
        $this->recordTrace();

        return $this->newUntypedActivityStub($options)->execute($type, $args, $returnType);
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedActivityStub(ActivityOptions $options = null): ActivityStubInterface
    {
        $this->recordTrace();
        $options ??= new ActivityOptions();

        return new ActivityStub($this->services->marshaller, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(string $class, ActivityOptions $options = null): object
    {
        $this->recordTrace();
        $activities = $this->services->activitiesReader->fromClass($class);

        return new ActivityProxy(
            $class,
            $activities,
            $this->newUntypedActivityStub($options)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function timer($interval): PromiseInterface
    {
        $this->recordTrace();

        return $this->request(
            new NewTimer(DateInterval::parse($interval, DateInterval::FORMAT_SECONDS))
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getTrace(): array
    {
        return $this->trace;
    }
}
