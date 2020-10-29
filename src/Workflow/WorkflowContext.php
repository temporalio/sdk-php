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
use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Worker\FactoryInterface;
use Temporal\Client\Worker\Worker;
use Temporal\Client\Workflow\Command\CompleteWorkflow;
use Temporal\Client\Workflow\Command\ExecuteActivity;
use Temporal\Client\Workflow\Command\NewTimer;

/**
 * @psalm-type WorkflowContextParams = array {
 *      name: string,
 *      wid: string,
 *      rid: string,
 *      taskQueue?: string,
 *      payload?: mixed,
 * }
 */
final class WorkflowContext implements WorkflowContextInterface
{
    /**
     * @var string
     */
    private const KEY_NAME = 'name';

    /**
     * @var string
     */
    private const KEY_WORKFLOW_ID = 'wid';

    /**
     * @var string
     */
    private const KEY_WORKFLOW_RUN_ID = 'rid';

    /**
     * @var string
     */
    private const KEY_TASK_QUEUE = 'taskQueue';

    /**
     * @var string
     */
    private const KEY_ARGUMENTS = 'args';

    /**
     * @psalm-var WorkflowContextParams
     * @var array
     */
    private array $params;

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
     * @param Worker $worker
     * @param RunningWorkflows $running
     * @param array $params
     */
    public function __construct(Worker $worker, RunningWorkflows $running, array $params)
    {
        $this->params = $params;
        $this->worker = $worker;
        $this->running = $running;
    }

    /**
     * @param string $name
     * @return ActivityProxy
     */
    public function activity(string $name): ActivityProxy
    {
        return new ActivityProxy($name, $this);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->params[self::KEY_NAME] ?? 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function getId(): string
    {
        return $this->params[self::KEY_WORKFLOW_ID] ?? 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function getTaskQueue(): string
    {
        return $this->params[self::KEY_TASK_QUEUE] ?? FactoryInterface::DEFAULT_TASK_QUEUE;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return (array)($this->params[self::KEY_ARGUMENTS] ?? []);
    }

    /**
     * @return array|int[]
     */
    public function getSendRequestIdentifiers(): array
    {
        return \array_values($this->requests);
    }

    /**
     * @return \DateTimeInterface
     */
    public function now(): \DateTimeInterface
    {
        return $this->worker->getTickTime();
    }

    /**
     * {@inheritDoc}
     */
    public function complete($result = null): PromiseInterface
    {
        $then = function ($result) {
            $this->running->kill($this->getRunId(), $this->worker->getClient());

            return $result;
        };

        $request = new CompleteWorkflow($result, \array_values($this->requests));

        return $this->request($request)
            ->then($then, $then)
            ;
    }

    /**
     * {@inheritDoc}
     */
    public function getRunId(): string
    {
        return $this->params[self::KEY_WORKFLOW_RUN_ID] ?? 'unknown';
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    protected function request(RequestInterface $request): PromiseInterface
    {
        $this->requests[] = $request->getId();

        $client = $this->worker->getClient();

        $then = function ($result) use ($request) {
            $index = \array_search($request->getId(), $this->requests, true);
            unset($this->requests[$index]);

            return $result;
        };

        return $client->request($request)
            ->then($then, $then)
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
        $request = new NewTimer(NewTimer::parseInterval($interval));

        return $this->request($request);
    }
}
