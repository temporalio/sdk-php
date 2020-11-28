<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Worker\OldTaskQueue;
use Temporal\Client\Internal\Workflow\RunningWorkflows;
use Temporal\Client\Internal\Workflow\WorkflowContext;
use Temporal\Client\Workflow\WorkflowInfo;

final class StartWorkflow extends Route
{
    /**
     * @var string
     */
    private const ERROR_NOT_FOUND = 'Workflow with the specified name "%s" was not registered';

    /**
     * @var string
     */
    private const ERROR_ALREADY_RUNNING = 'Workflow "%s" with run id "%s" has been already started';

    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $running;

    /**
     * @var Collection<WorkflowPrototype>
     */
    private Collection $workflows;

    /**
     * @var OldTaskQueue
     */
    private OldTaskQueue $worker;

    /**
     * @param Collection<WorkflowPrototype> $workflows
     * @param RunningWorkflows $running
     * @param OldTaskQueue $worker
     */
    public function __construct(Collection $workflows, RunningWorkflows $running, OldTaskQueue $worker)
    {
        $this->running = $running;
        $this->workflows = $workflows;
        $this->worker = $worker;
    }

    /**
     * {@inheritDoc}
     * @throws \Throwable
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $context = $this->createContext($payload);
        $info = $context->getInfo();

        $process = $this->running->run($this->worker, $context, $this->findWorkflowOrFail($info));

        $process->next();
        $resolver->resolve(['WorkflowExecution' => $info->execution]);
    }

    /**
     * @param array $payload
     * @return WorkflowContext
     * @throws \Exception
     */
    private function createContext(array $payload): WorkflowContext
    {
        return new WorkflowContext($this->worker, $this->running, $payload);
    }

    /**
     * @param WorkflowInfo $info
     * @return WorkflowPrototype
     */
    private function findWorkflowOrFail(WorkflowInfo $info): WorkflowPrototype
    {
        $workflow = $this->workflows->find($info->type->name);

        if ($workflow === null) {
            throw new \OutOfRangeException(\sprintf(self::ERROR_NOT_FOUND, $info->type->name));
        }

        if ($this->running->find($info->execution->runId) !== null) {
            $message = \sprintf(self::ERROR_ALREADY_RUNNING, $info->type->name, $info->execution->runId);

            throw new \LogicException($message);
        }

        return $workflow;
    }
}
