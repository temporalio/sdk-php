<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Activity\ActivityWorker;
use Temporal\Client\Activity\ActivityDeclarationInterface;
use Temporal\Client\Worker\Env\EnvironmentInterface;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;
use Temporal\Client\Meta\ReaderInterface;
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
     * @param string $taskQueue
     * @param ReaderInterface|null $reader
     * @param EnvironmentInterface $env
     * @throws \Exception
     */
    public function __construct(string $taskQueue, ReaderInterface $reader, EnvironmentInterface $env)
    {
        $this->env = $env;
        $this->taskQueue = $taskQueue;

        $this->workflowWorker = new WorkflowWorker($reader, $taskQueue);
        $this->activityWorker = new ActivityWorker($reader, $taskQueue);
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
    public function getWorkflows(): iterable
    {
        return $this->workflowWorker->getWorkflows();
    }

    /**
     * {@inheritDoc}
     */
    public function registerActivity(object $activity, bool $overwrite = false): self
    {
        return $this->activityWorker->registerActivity($activity, $overwrite);
    }

    /**
     * {@inheritDoc}
     */
    public function registerActivityDeclaration(ActivityDeclarationInterface $activity, bool $overwrite = false): self
    {
        return $this->activityWorker->registerActivityDeclaration($activity, $overwrite);
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
    public function getActivities(): iterable
    {
        return $this->activityWorker->getActivities();
    }

    /**
     * @param string $body
     * @param array $context
     * @return string
     */
    public function emit(string $body, array $context = []): string
    {
        switch ($this->env->get()) {
            case EnvironmentInterface::ENV_WORKFLOW:
                return $this->workflowWorker->emit($body, $context);

            case EnvironmentInterface::ENV_ACTIVITY:
                return $this->activityWorker->emit($body, $context);

            default:
                throw new \LogicException('Unsupported environment type');
        }
    }
}
