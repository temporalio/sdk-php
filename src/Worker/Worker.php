<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Activity\ActivityDeclarationInterface;
use Temporal\Client\Activity\ActivityWorker;
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;
use Temporal\Client\Worker\Env\EnvironmentInterface;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;
use Temporal\Client\Workflow\WorkflowWorker;

class Worker implements WorkerInterface
{
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
     * @var EnvironmentInterface
     */
    private EnvironmentInterface $env;

    /**
     * @param ClientInterface $c
     * @param ReaderInterface $reader
     * @param EnvironmentInterface $env
     * @param string $queue
     * @throws \Exception
     */
    public function __construct(ClientInterface $c, ReaderInterface $reader, EnvironmentInterface $env, string $queue)
    {
        $this->env = $env;
        $this->taskQueue = $queue;

        $this->workflowWorker = new WorkflowWorker($c, $reader, $queue);
        $this->activityWorker = new ActivityWorker($reader, $queue);
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
    public function dispatch(RequestInterface $request, array $headers = []): ResponseInterface
    {
        switch ($this->env->get()) {
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
    public function getWorkflows(): iterable
    {
        return $this->workflowWorker->getWorkflows();
    }

    /**
     * {@inheritDoc}
     */
    public function getActivities(): iterable
    {
        return $this->activityWorker->getActivities();
    }
}
