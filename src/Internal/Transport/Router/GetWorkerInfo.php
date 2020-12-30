<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Worker\TaskQueueInterface;

final class GetWorkerInfo extends Route
{
    /**
     * @var RepositoryInterface<TaskQueueInterface>
     */
    private RepositoryInterface $queues;

    /**
     * @param RepositoryInterface<TaskQueueInterface> $queues
     */
    public function __construct(RepositoryInterface $queues)
    {
        $this->queues = $queues;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $result = [];

        foreach ($this->queues as $taskQueue) {
            $result[] = $this->workerToArray($taskQueue);
        }

        $resolver->resolve($result);
    }

    /**
     * @param TaskQueueInterface $taskQueue
     * @return array
     */
    private function workerToArray(TaskQueueInterface $taskQueue): array
    {
        return [
            'taskQueue'  => $taskQueue->getId(),
            'workflows'  => $this->map($taskQueue->getWorkflows(), function (WorkflowPrototype $workflow) {
                return [
                    'name'    => $workflow->getId(),
                    'queries' => $this->keys($workflow->getQueryHandlers()),
                    'signals' => $this->keys($workflow->getSignalHandlers()),
                ];
            }),
            'activities' => $this->map($taskQueue->getActivities(), function (ActivityPrototype $activity) {
                return [
                    'name' => $activity->getId(),
                ];
            }),
        ];
    }

    /**
     * @param iterable $items
     * @param \Closure $map
     * @return array
     */
    private function map(iterable $items, \Closure $map): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $result[] = $map($value, $key);
        }

        return $result;
    }

    /**
     * @param iterable $items
     * @return array
     */
    private function keys(iterable $items): array
    {
        $result = [];

        foreach ($items as $key => $_) {
            $result[] = $key;
        }

        return $result;
    }
}
