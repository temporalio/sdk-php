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
use Temporal\Exception\CancellationException;
use Temporal\Internal\Transport\Request\ContinueAsNew;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Payload;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Transport\CapturedClient;
use Temporal\Internal\Transport\CapturedClientInterface;
use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Internal\Transport\Request\SideEffect;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Internal\Workflow\ChildWorkflowProxy;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\CancellationScope;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Worker\Command\RequestInterface;

use function React\Promise\reject;

class WorkflowContext implements WorkflowContextInterface
{
    /**
     * @var ServiceContainer
     */
    protected ServiceContainer $services;

    /**
     * @var CapturedClientInterface
     */
    protected CapturedClientInterface $client;

    /**
     * @var Input
     */
    private Input $input;

    /**
     * @var Process
     */
    private Process $process;

    /**
     * @var Scope
     */
    private Scope $lastScope;

    /**
     * @var array
     */
    private array $trace = [];

    /**
     * @var bool
     */
    private bool $continueAsNew = false;

    /**
     * When marked as cancelled requests are forbidden.
     *
     * @var bool
     */
    private bool $cancelled = false;

    /**
     * @param Process $process
     * @param ServiceContainer $services
     * @param Input $input
     */
    public function __construct(Process $process, ServiceContainer $services, Input $input)
    {
        $this->process = $process;
        $this->lastScope = $process;
        $this->input = $input;
        $this->services = $services;

        $this->client = new CapturedClient($services->client);
    }

    /**
     * Invalidate context (no longer accept any requests).
     */
    public function invalidate()
    {
        $this->cancelled = true;
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeZone(): CarbonTimeZone
    {
        $this->recordTrace();

        return $this->services->env->getTimeZone();
    }

    /**
     * Record last stack trace of the call.
     *
     * @return void
     */
    private function recordTrace(): void
    {
        $this->trace = \debug_backtrace(
            \DEBUG_BACKTRACE_IGNORE_ARGS
        );
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
    public function getArguments(): array
    {
        $this->recordTrace();

        return $this->input->args;
    }

    /**
     * @return DataConverterInterface
     */
    public function getDataConverter(): DataConverterInterface
    {
        return $this->services->dataConverter;
    }

    /**
     * @return CapturedClientInterface
     */
    public function getClient(): CapturedClientInterface
    {
        return $this->client;
    }

    /**
     * {@inheritDoc}
     */
    public function registerQuery(string $queryType, callable $handler): WorkflowContextInterface
    {
        $this->recordTrace();

        $instance = $this->process->getWorkflowInstance();
        $instance->addQueryHandler($queryType, $handler);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function registerSignal(string $queryType, callable $handler): WorkflowContextInterface
    {
        $this->recordTrace();

        $instance = $this->process->getWorkflowInstance();
        $instance->addSignalHandler($queryType, $handler);

        return $this;
    }

    /**
     * @param callable $handler
     * @return CancellationScope
     */
    public function newCancellationScope(callable $handler): CancellationScope
    {
        $this->recordTrace();

        $self = immutable(fn() => $this->client = new CapturedClient($this->client));

        $scope = new CancellationScope($self, $this->services, \Closure::fromCallable($handler));
        $self->lastScope = $scope;

        $this->lastScope->onCancel([$scope, 'cancel']);

        return $scope;
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

        $self = immutable(fn() => $this->client = new CapturedClient($this->client));
        $self->cancelled = false;

        return new CancellationScope($self, $this->services, \Closure::fromCallable($handler));
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        $this->recordTrace();

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

        if ($this->cancelled) {
            throw new CancellationException("Attempt to send request to cancelled context");
        }

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
        return $this->toResponse($this->request(new SideEffect($value)));
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
    public function complete($result = null): PromiseInterface
    {
        $this->recordTrace();

        $then = function ($result) {
            $this->process->cancel();

            return $result;
        };

        /** @psalm-suppress UnusedClosureParam */
        $otherwise = function (\Throwable $error): void {
            $this->process->cancel();

            throw $error;
        };

        // must not be captured
        return $this->services->client->request(new CompleteWorkflow($result))
            ->then($then, $otherwise);
    }

    /**
     * {@inheritDoc}
     */
    public function continueAsNew(string $name, ...$input): PromiseInterface
    {
        $this->continueAsNew = true;
        $this->recordTrace();

        $then = function ($result) {
            $this->process->cancel();

            return $result;
        };

        $otherwise = function (\Throwable $error): void {
            $this->process->cancel();

            throw $error;
        };

        // must not be captured
        return $this->services->client->request(new ContinueAsNew($name, $input))->then($then, $otherwise);
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

        $options = $this->services->marshaller->marshal(
            $options ?? new ChildWorkflowOptions()
        );

        return $this->toResponse(
            $this->request(new ExecuteChildWorkflow($type, $args, $options)),
            $returnType
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null): object
    {
        $this->recordTrace();

        $options ??= new ChildWorkflowOptions();

        return new ChildWorkflowProxy($class, $options, $this, $this->services->workflows);
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

        $options = $this->services->marshaller->marshal(
            $options ?? new ActivityOptions()
        );

        return $this->toResponse(
            $this->request(new ExecuteActivity($type, $args, $options)),
            $returnType
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(string $class, ActivityOptions $options = null): object
    {
        $this->recordTrace();

        $options ??= new ActivityOptions();

        return new ActivityProxy($class, $options, $this, $this->services->activities);
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

    /**
     * Unpack the server response into internal format based on return or argument type.
     *
     * @param PromiseInterface $promise
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     */
    private function toResponse(PromiseInterface $promise, \ReflectionType $returnType = null)
    {
        return $promise->then(
            function ($value) use ($returnType) {
                if (!$value instanceof Payload || $value instanceof \Throwable) {
                    return $value;
                }

                return $this->getDataConverter()->fromPayload($value, $returnType);
            }
        );
    }
}
