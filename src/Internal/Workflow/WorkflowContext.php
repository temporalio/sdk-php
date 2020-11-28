<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow;

use JetBrains\PhpStorm\ExpectedValues;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Marshaller\MarshallerInterface;
use Temporal\Client\Internal\Support\DateInterval;
use Temporal\Client\Internal\Transport\Future;
use Temporal\Client\Internal\Transport\FutureInterface;
use Temporal\Client\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Client\Internal\Transport\Request\ExecuteActivity;
use Temporal\Client\Internal\Transport\Request\GetVersion;
use Temporal\Client\Internal\Transport\Request\NewTimer;
use Temporal\Client\Internal\Transport\Request\SideEffect;
use Temporal\Client\Internal\Worker\OldTaskQueue;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Workflow\ActivityProxy;
use Temporal\Client\Workflow\WorkflowInfo;

use function React\Promise\reject;

/**
 * @deprecated
 */
final class WorkflowContext implements WorkflowContextInterface
{
    /**
     * @var string
     */
    private const KEY_INFO = 'info';

    /**
     * @var string
     */
    private const KEY_ARGUMENTS = 'args';

    /**
     * @var OldTaskQueue
     */
    private OldTaskQueue $worker;

    /**
     * @var array|int[]
     */
    private array $requests = [];

    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $running;

    /**
     * @var WorkflowInfo
     */
    private WorkflowInfo $info;

    /**
     * @var string[]
     */
    private array $arguments;

    /**
     * @var array
     */
    private array $lastStacktrace;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param OldTaskQueue $worker
     * @param RunningWorkflows $running
     * @param array $params
     * @throws \Exception
     */
    public function __construct(OldTaskQueue $worker, RunningWorkflows $running, array $params)
    {
        $this->worker = $worker;
        $this->running = $running;
        $this->marshaller = $worker->getMarshaller();

        $this->info = $this->marshaller->unmarshal($params[self::KEY_INFO], new WorkflowInfo());
        $this->arguments = $params[self::KEY_ARGUMENTS] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(
        string $name,
        #[ExpectedValues(values: ActivityOptions::class)]
        $options = null
    ): ActivityProxy {
        $this->recordStacktrace();

        // Create defaults if options not created
        if ($options === null || \is_array($options)) {
            $options = $this->marshaller->unmarshal((array)$options, new ActivityOptions());
        }

        return new ActivityProxy($name, $options, $this, $this->worker->getActivities());
    }

    /**
     * Record last stack trace of the call.
     */
    private function recordStacktrace(): void
    {
        // raw information
        $this->lastStacktrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    /**
     * {@inheritDoc}
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * {@inheritDoc}
     */
    public function getInfo(): WorkflowInfo
    {
        $this->recordStacktrace();

        return $this->info;
    }

    /**
     * @return array
     */
    public function getDebugBacktrace(): array
    {
        return $this->lastStacktrace;
    }

    /**
     * @return int[]
     */
    public function getRequestIdentifiers(): array
    {
        return \array_values($this->requests);
    }

    /**
     * {@inheritDoc}
     */
    public function now(): \DateTimeInterface
    {
        $this->recordStacktrace();

        return $this->worker->getTickTime();
    }

    /**
     * @param string $changeId
     * @param positive-int $minSupported
     * @param positive-int $maxSupported
     * @return PromiseInterface
     * @throws \Throwable
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        return $this->request(new GetVersion($changeId, $minSupported, $maxSupported));
    }

    /**
     * @param RequestInterface $request
     * @return FutureInterface
     */
    private function request(RequestInterface $request): FutureInterface
    {
        $this->recordStacktrace();

        $this->requests[] = $request->getId();

        $client = $this->worker->getClient();

        $then = function ($result) use ($request) {
            $this->recordStacktrace();
            Workflow::setCurrentContext($this);
            $this->unload($request);

            return $result;
        };

        /** @psalm-suppress UnusedClosureParam */
        $otherwise = function (\Throwable $exception) use ($request) {
            $this->recordStacktrace();
            Workflow::setCurrentContext($this);
            $this->unload($request);

            throw $exception;
        };

        /** @var CancellablePromiseInterface $result */
        $result = $client->request($request)
            ->then($then, $otherwise)
        ;

        return new Future($result, $this->worker);
    }

    /**
     * @param RequestInterface $request
     */
    private function unload(RequestInterface $request): void
    {
        $index = \array_search($request->getId(), $this->requests, true);

        unset($this->requests[$index]);
    }

    /**
     * {@inheritDoc}
     */
    public function sideEffect(callable $cb): PromiseInterface
    {
        try {
            if ($this->isReplaying()) {
                $value = null;
            } else {
                $value = $cb();
            }

            $request = new SideEffect($value);
        } catch (\Throwable $e) {
            return reject($e);
        }

        return $this->request($request);
    }

    /**
     * {@inheritDoc}
     */
    public function isReplaying(): bool
    {
        $this->recordStacktrace();

        $workflow = $this->worker->getWorkflowWorker();

        return $workflow->isReplaying();
    }

    /**
     * @param string $queryType
     * @param callable $handler
     * @return $this
     */
    public function registerQuery(string $queryType, callable $handler): self
    {
        $this->findCurrentProcessOrFail()
            ->getInstance()
            ->addQueryHandler($queryType, $handler)
        ;

        return $this;
    }

    /**
     * @return Process
     */
    private function findCurrentProcessOrFail(): Process
    {
        $process = $this->worker
            ->getWorkflowWorker()
            ->getRunningWorkflows()
            ->find($this->info->execution->runId)
        ;

        if ($process === null) {
            throw new \DomainException('Process has been destroyed');
        }

        return $process;
    }

    /**
     * @param string $queryType
     * @param callable $handler
     * @return $this
     */
    public function registerSignal(string $signalType, callable $handler): self
    {
        $this->findCurrentProcessOrFail()
            ->getInstance()
            ->addSignalHandler($signalType, $handler)
        ;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function complete($result = null): PromiseInterface
    {
        $request = new CompleteWorkflow($result, \array_values($this->requests));

        $onFulfilled = function ($result) {
            $this->running->kill($this->info->execution->runId, $this->worker->getClient());

            return $result;
        };

        /** @psalm-suppress UnusedClosureParam */
        $onRejected = function (\Throwable $exception) {
            $this->running->kill($this->info->execution->runId, $this->worker->getClient());

            throw $exception;
        };

        return $this->request($request)
            ->then($onFulfilled, $onRejected)
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function executeActivity(
        string $name,
        array $arguments = [],
        #[ExpectedValues(values: ActivityOptions::class)]
        $options = null
    ): PromiseInterface {
        // Create defaults if options object not created
        if ($options === null || \is_array($options)) {
            $options = $this->marshaller->unmarshal((array)$options, new ActivityOptions());
        }

        $request = new ExecuteActivity($name, $arguments, $this->marshaller->marshal($options));

        return $this->request($request);
    }

    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    public function timer($interval, string $format = DateInterval::FORMAT_SECONDS): PromiseInterface
    {
        $request = new NewTimer(DateInterval::parse($interval, $format));

        return $this->request($request);
    }

    /**
     * @param callable $handler
     * @return CancellationScope
     */
    public function newCancellationScope(callable $handler): CancellationScope
    {
        $process = $this->findCurrentProcessOrFail();

        return new CancellationScope($process, $handler);
    }
}
