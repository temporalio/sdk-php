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
use Temporal\Client\Future\Future;
use Temporal\Client\Future\FutureInterface;
use Temporal\Client\Support\DateInterval;
use Temporal\Client\Transport\Protocol\Command\RequestInterface;
use Temporal\Client\Worker\Worker;
use Temporal\Client\Workflow\Command\CompleteWorkflow;
use Temporal\Client\Workflow\Command\ExecuteActivity;
use Temporal\Client\Workflow\Command\NewTimer;
use Temporal\Client\Workflow\Command\SideEffect;

final class WorkflowEnvironment implements WorkflowEnvironmentInterface
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
    public function isReplaying(): bool
    {
        $workflow = $this->worker->getWorkflowWorker();

        return $workflow->isReplaying();
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(string $name): ActivityProxy
    {
        return new ActivityProxy($name, $this);
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
        return $this->info;
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
        return $this->worker->getTickTime();
    }

    /**
     * {@inheritDoc}
     */
    public function sideEffect(callable $cb): PromiseInterface
    {
        try {
            $request = new SideEffect($cb());
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
        $this->requests[] = $request->getId();

        $client = $this->worker->getClient();

        $then = function ($result) use ($request) {
            $this->unload($request);

            return $result;
        };

        $otherwise = function (\Throwable $e) use ($request) {
            $this->unload($request);

            throw $e;
        };

        /** @var CancellablePromiseInterface $result */
        $result = $client->request($request)
            ->then($then, $otherwise)
        ;

        return new Future($result);
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

        return new Future($this->request($request));
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
