<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Worker\WorkerInterface;
use Temporal\Client\Workflow\RunningWorkflows;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;

final class InvokeSignal extends Route
{
    /**
     * @var string
     */
    private const ERROR_RID_NOT_DEFINED =
        'Invoking query of a workflow requires the id (rid argument) ' .
        'of the running workflow process';

    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_FOUND = 'Workflow with the specified run id %s not found';

    /**
     * @var string
     */
    private const ERROR_SIGNAL_NOT_FOUND = 'Workflow signal handler "%s" not found, known signals [%s]';

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
     * @var WorkerInterface
     */
    private WorkerInterface $worker;

    /**
     * @psalm-param CollectionInterface<WorkflowDeclarationInterface> $workflows
     *
     * @param RunningWorkflows $running
     * @param CollectionInterface $workflows
     * @param WorkerInterface $worker
     */
    public function __construct(CollectionInterface $workflows, RunningWorkflows $running, WorkerInterface $worker)
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
        $workflowRunId = $payload['runId'] ?? null;

        if ($workflowRunId === null) {
            throw new \InvalidArgumentException(self::ERROR_RID_NOT_DEFINED);
        }

        $workflow = $this->running->find($workflowRunId);

        if ($workflow === null) {
            throw new \LogicException(\sprintf(self::ERROR_PROCESS_NOT_FOUND, $workflowRunId));
        }

        $declaration = $workflow->getDeclaration();

        $handler = $declaration->findSignalHandler($payload['name']);

        if ($handler === null) {
            throw new \LogicException(\vsprintf(self::ERROR_SIGNAL_NOT_FOUND, [
                $payload['name'],
                \implode(', ', [...$this->getAvailableSignalNames()]),
            ]));
        }

        $arguments = $payload['args'] ?? [];

        $this->worker->once(WorkerInterface::ON_SIGNAL,
            static fn() => $resolver->resolve($handler(...$arguments))
        );
    }

    /**
     * @return iterable|string[]
     */
    private function getAvailableSignalNames(): iterable
    {
        /** @var WorkflowDeclarationInterface $workflow */
        foreach ($this->workflows as $workflow) {
            foreach ($workflow->getSignalHandlers() as $name => $_) {
                yield $name;
            }
        }
    }
}