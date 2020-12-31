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
     * @var array
     */
    private array $trace = [];

    /**
     * @param Process $process
     * @param ServiceContainer $services
     * @param Input $input
     */
    public function __construct(Process $process, ServiceContainer $services, Input $input)
    {
        $this->process = $process;
        $this->input = $input;
        $this->services = $services;

        $this->client = new CapturedClient($services->client);
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

        $value = \current($this->getDataConverter()->toPayloads([$value]));

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
        if (! $result instanceof Payload && ! $result instanceof \Throwable) {
            [$result] = $this->getDataConverter()
                ->toPayloads([$result])
            ;
        }

        $this->recordTrace();

        $then = function ($result) {
            $this->process->cancel();

            return $result;
        };

        $otherwise = function (\Throwable $error): void {
            $this->process->cancel();

            throw $error;
        };

        return $this->request(new CompleteWorkflow($result))
            ->then($then, $otherwise);
    }

    /**
     * {@inheritDoc}
     */
    public function executeChildWorkflow(
        string $type,
        array $args = [],
        ChildWorkflowOptions $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface
    {
        $args = $this->getDataConverter()->toPayloads($args);

        $this->recordTrace();

        $options = $this->services->marshaller->marshal(
            $options ?? new ChildWorkflowOptions()
        );

        return $this->toResponse($this->request(
            new ExecuteChildWorkflow($type, $args, $options)
        ));
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
    public function executeActivity(string $type, array $args = [], ActivityOptions $options = null): PromiseInterface
    {
        $this->recordTrace();

        $options = $this->services->marshaller->marshal(
            $options ?? new ActivityOptions()
        );

        return $this->toResponse($this->request(
            new ExecuteActivity($type, $this->getDataConverter()->toPayloads($args), $options)
        ));
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
     * @param PromiseInterface $promise
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     */
    private function toResponse(PromiseInterface $promise, \ReflectionType $returnType = null)
    {
        return $promise->then(function ($value) use ($returnType) {
            // todo: improve it?
            if (!$value instanceof Payload || $value instanceof \Throwable) {
                return $value;
            }

            return $this->getDataConverter()->fromPayloads([$value], $returnType ? [$returnType] : [])[0];
        });
    }
}
