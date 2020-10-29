<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Router;

use React\Promise\Deferred;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Worker\Worker;
use Temporal\Client\Workflow\RunningWorkflows;
use Temporal\Client\Workflow\WorkflowContext;
use Temporal\Client\Workflow\WorkflowContextInterface;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;

final class StartWorkflow extends Route
{
    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $running;

    /**
     * @psalm-var CollectionInterface<WorkflowDeclarationInterface>
     *
     * @var CollectionInterface
     */
    private CollectionInterface $workflows;

    /**
     * @var Worker
     */
    private Worker $worker;

    /**
     * @psalm-param CollectionInterface<WorkflowDeclarationInterface> $workflows
     *
     * @param CollectionInterface $workflows
     * @param RunningWorkflows $running
     * @param Worker $worker
     */
    public function __construct(CollectionInterface $workflows, RunningWorkflows $running, Worker $worker)
    {
        $this->running = $running;
        $this->workflows = $workflows;
        $this->worker = $worker;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $context = new WorkflowContext($this->worker, $this->running, $payload);

        $this->assertNotRunning($context);

        $process = $this->running->run($context, $this->findDeclarationOrFail($context));

        $process->start($context->getPayload());

        dump('STARTED');

        $resolver->resolve(['wid' => $context->getId(), 'rid' => $context->getRunId()]);

        $process->next();
    }

    /**
     * @param WorkflowContextInterface $context
     */
    private function assertNotRunning(WorkflowContextInterface $context): void
    {
        $isRunning = $this->running->find($context->getRunId()) !== null;

        if (! $isRunning) {
            return;
        }

        $error = \sprintf('Workflow with run id %s has been already started', $context->getRunId());
        throw new \LogicException($error);
    }

    /**
     * @param WorkflowContextInterface $context
     * @return WorkflowDeclarationInterface
     */
    private function findDeclarationOrFail(WorkflowContextInterface $context): WorkflowDeclarationInterface
    {
        /** @var WorkflowDeclarationInterface $workflow */
        $workflow = $this->workflows->find($context->getName());

        if ($workflow === null) {
            $error = \sprintf('Workflow with the specified name %s was not registered', $context->getName());
            throw new \LogicException($error);
        }

        return $workflow;
    }
}
