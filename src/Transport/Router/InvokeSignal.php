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
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Declaration\WorkflowInstance;
use Temporal\Client\Worker\WorkerInterface;
use Temporal\Client\Workflow\RunningWorkflows;

final class InvokeSignal extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_SIGNAL_NOT_FOUND = 'unknown signalType %s. KnownSignalTypes=[%s]';

    /**
     * @var Collection<WorkflowPrototype>
     */
    private Collection $workflows;

    /**
     * @var WorkerInterface
     */
    private WorkerInterface $worker;

    /**
     * @param Collection<WorkflowPrototype> $workflows
     * @param RunningWorkflows $running
     * @param WorkerInterface $worker
     */
    public function __construct(Collection $workflows, RunningWorkflows $running, WorkerInterface $worker)
    {
        $this->workflows = $workflows;
        $this->worker = $worker;

        parent::__construct($running);
    }

    /**
     * @return iterable|string[]
     */
    private function getAvailableSignalNames(): iterable
    {
        foreach ($this->workflows as $workflow) {
            foreach ($workflow->getSignalHandlers() as $name => $_) {
                yield $name;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        ['runId' => $runId, 'name' => $name] = $payload;

        $instance = $this->findInstanceOrFail($runId);
        $handler = $this->findSignalHandlerOrFail($instance, $name);

        $executor = static fn() => $resolver->resolve($handler($payload['args'] ?? []));
        $this->worker->once(WorkerInterface::ON_SIGNAL, $executor);
    }

    /**
     * @param WorkflowInstance $instance
     * @param string $name
     * @return \Closure|null
     */
    private function findSignalHandlerOrFail(WorkflowInstance $instance, string $name): ?\Closure
    {
        $handler = $instance->findQueryHandler($name);

        if ($handler === null) {
            $available = \implode(' ', [...$this->getAvailableSignalNames()]);

            throw new \LogicException(\sprintf(self::ERROR_SIGNAL_NOT_FOUND, $name, $available));
        }

        return $handler;
    }
}
