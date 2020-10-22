<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Router;

use React\Promise\Deferred;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;
use Temporal\Client\Workflow\Protocol\WorkflowProtocolInterface;
use Temporal\Client\Workflow\Runtime\RunningWorkflows;
use Temporal\Client\Workflow\Runtime\WorkflowContext;
use Temporal\Client\Workflow\Runtime\WorkflowContextInterface;

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
     * @var WorkflowProtocolInterface
     */
    private WorkflowProtocolInterface $protocol;

    /**
     * @param CollectionInterface<WorkflowDeclarationInterface> $workflows
     * @param RunningWorkflows $running
     * @param WorkflowProtocolInterface $protocol
     */
    public function __construct(
        CollectionInterface $workflows,
        RunningWorkflows $running,
        WorkflowProtocolInterface $protocol
    ) {
        $this->running = $running;
        $this->workflows = $workflows;
        $this->protocol = $protocol;
    }

    /**
     * @param array $params
     * @param Deferred $resolver
     */
    public function handle(array $params, Deferred $resolver): void
    {
        $context = new WorkflowContext($this->protocol, $params);

        $this->assertNotRunning($context);

        $process = $this->running->run($context, $this->findDeclarationOrFail($context));
        $resolver->resolve(['wid' => $context->getId(), 'rid' => $context->getRunId()]);

        $process->start($context->getPayload());
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

        $error = \sprintf('Workflow with run id #%s has been already started', $context->getRunId());
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
