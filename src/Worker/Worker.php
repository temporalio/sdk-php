<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Carbon\Carbon;
use Evenement\EventEmitterTrait;
use JetBrains\PhpStorm\Pure;
use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityDeclarationInterface;
use Temporal\Client\Activity\ActivityWorker;
use Temporal\Client\Transport\ClientInterface;
use Temporal\Client\Transport\Protocol\Command\RequestInterface;
use Temporal\Client\Worker\Env\EnvironmentInterface;
use Temporal\Client\WorkerFactory;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;
use Temporal\Client\Workflow\WorkflowWorker;

class Worker implements WorkerInterface
{
    use EventEmitterTrait;

    /**
     * @var WorkflowWorker
     */
    private WorkflowWorker $workflowWorker;

    /**
     * @var ActivityWorker
     */
    private ActivityWorker $activityWorker;

    /**
     * @var string
     */
    private string $taskQueue;

    /**
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $now;

    /**
     * @var \DateTimeZone
     */
    private \DateTimeZone $zone;

    /**
     * @var WorkerFactory
     */
    private WorkerFactory $factory;

    /**
     * @var \Closure
     */
    private \Closure $factoryEventListener;

    /**
     * @param WorkerFactory $factory
     * @param EnvironmentInterface $env
     * @param string $queue
     * @throws \Exception
     */
    public function __construct(WorkerFactory $factory, string $queue)
    {
        $this->taskQueue = $queue;
        $this->factory = $factory;
        $this->zone = new \DateTimeZone('UTC');
        $this->now = new \DateTimeImmutable('now', $this->zone);

        $this->workflowWorker = new WorkflowWorker($this, $this->factory->getReader());
        $this->activityWorker = new ActivityWorker($this, $this->factory->getReader());

        $this->factoryEventListener = function () {
            $this->emit(self::ON_SIGNAL);
            $this->emit(self::ON_CALLBACK);
            $this->emit(self::ON_QUERY);
            $this->emit(self::ON_TICK);
        };

        $this->boot();
    }

    /**
     * @return void
     */
    private function boot(): void
    {
        $this->attachFactoryListener();
    }

    /**
     * @return void
     */
    private function attachFactoryListener(): void
    {
        $this->factory->on(LoopInterface::ON_TICK, $this->factoryEventListener);
    }

    /**
     * @return void
     */
    private function detachFactoryListener(): void
    {
        $this->factory->removeListener(LoopInterface::ON_TICK, $this->factoryEventListener);
    }

    /**
     * @return WorkflowWorker
     */
    public function getWorkflowWorker(): WorkflowWorker
    {
        return $this->workflowWorker;
    }

    /**
     * @return ActivityWorker
     */
    public function getActivityWorker(): ActivityWorker
    {
        return $this->activityWorker;
    }

    /**
     * @return \DateTimeInterface
     */
    public function now(): \DateTimeInterface
    {
        return $this->now;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getTickTime(): \DateTimeInterface
    {
        return $this->now;
    }

    /**
     * @return ClientInterface
     */
    public function getClient(): ClientInterface
    {
        return $this->factory->getClient();
    }

    /**
     * {@inheritDoc}
     */
    public function registerWorkflow(object $workflow, bool $overwrite = false): self
    {
        $this->workflowWorker->registerWorkflow($workflow, $overwrite);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function registerWorkflowDeclaration(WorkflowDeclarationInterface $workflow, bool $overwrite = false): self
    {
        $this->workflowWorker->registerWorkflowDeclaration($workflow, $overwrite);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function findWorkflow(string $name): ?WorkflowDeclarationInterface
    {
        return $this->workflowWorker->findWorkflow($name);
    }

    /**
     * {@inheritDoc}
     */
    public function registerActivity(object $activity, bool $overwrite = false): self
    {
        $this->activityWorker->registerActivity($activity, $overwrite);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function registerActivityDeclaration(ActivityDeclarationInterface $activity, bool $overwrite = false): self
    {
        $this->activityWorker->registerActivityDeclaration($activity, $overwrite);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function findActivity(string $name): ?ActivityDeclarationInterface
    {
        return $this->activityWorker->findActivity($name);
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(RequestInterface $request, array $headers = []): PromiseInterface
    {
        // Intercept headers
        if (isset($headers['tickTime'])) {
            $this->now = Carbon::parse($headers['tickTime'], $this->zone);
        }

        $environment = $this->factory->getEnvironment();

        switch ($environment->get()) {
            case EnvironmentInterface::ENV_WORKFLOW:
                return $this->workflowWorker->dispatch($request, $headers);

            case EnvironmentInterface::ENV_ACTIVITY:
                return $this->activityWorker->dispatch($request, $headers);

            default:
                throw new \LogicException('Unsupported environment type');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTaskQueue(): string
    {
        return $this->taskQueue;
    }

    /**
     * {@inheritDoc}
     */
    #[Pure]
    public function getWorkflows(): iterable
    {
        return $this->workflowWorker->getWorkflows();
    }

    /**
     * {@inheritDoc}
     */
    #[Pure]
    public function getActivities(): iterable
    {
        return $this->activityWorker->getActivities();
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->detachFactoryListener();
        $this->removeAllListeners();
    }
}
