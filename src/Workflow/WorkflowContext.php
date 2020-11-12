<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use JetBrains\PhpStorm\ExpectedValues;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Support\DateInterval;
use Temporal\Client\Transport\Future;
use Temporal\Client\Transport\FutureInterface;
use Temporal\Client\Transport\Protocol\Command\RequestInterface;
use Temporal\Client\Worker\Worker;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\Command\CompleteWorkflow;
use Temporal\Client\Workflow\Command\ExecuteActivity;
use Temporal\Client\Workflow\Command\GetVersion;
use Temporal\Client\Workflow\Command\NewTimer;
use Temporal\Client\Workflow\Command\SideEffect;

use function React\Promise\reject;

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
     * @var Worker
     */
    private Worker $worker;

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
     * @param Worker $worker
     * @param RunningWorkflows $running
     * @param array $params
     * @throws \Exception
     */
    public function __construct(Worker $worker, RunningWorkflows $running, array $params)
    {
        $this->worker = $worker;
        $this->running = $running;

        $this->info = WorkflowInfo::fromArray($params[self::KEY_INFO]);
        $this->arguments = $params[self::KEY_ARGUMENTS] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(
        string $name,
        #[ExpectedValues(values: ActivityOptions::class)]
        $options = null
    ): ActivityProxy
    {
        $this->recordStacktrace();

        return new ActivityProxy($name, $options, $this, $this->worker->getActivities());
    }

    /**
     * Record last stack trace of the call.
     */
    private function recordStacktrace()
    {
        // raw information
        $this->lastStacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
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
     * @param string $changeID
     * @param int $minSupported
     * @param int $maxSupported
     * @return PromiseInterface
     */
    public function getVersion(string $changeID, int $minSupported, int $maxSupported): PromiseInterface
    {
        try {
            $request = new GetVersion($changeID, $minSupported, $maxSupported);
        } catch (\Throwable $e) {
            return reject($e);
        }

        return $this->request($request);
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

        $otherwise = function (\Throwable $e) use ($request) {
            $this->recordStacktrace();
            Workflow::setCurrentContext($this);
            $this->unload($request);

            throw $e;
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
     * {@inheritDoc}
     */
    public function complete($result = null): PromiseInterface
    {
        $request = new CompleteWorkflow($result, \array_values($this->requests));

        $onFulfilled = function ($result) {
            $this->running->kill($this->info->execution->runId, $this->worker->getClient());

            return $result;
        };

        $onRejected = function (\Throwable $e) {
            $this->running->kill($this->info->execution->runId, $this->worker->getClient());

            throw $e;
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
        $request = new ExecuteActivity($name, $arguments, ActivityOptions::new($options));

        return $this->request($request);
    }

    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    public function timer($interval): PromiseInterface
    {
        $request = new NewTimer(DateInterval::parse($interval));

        return $this->request($request);
    }
}
