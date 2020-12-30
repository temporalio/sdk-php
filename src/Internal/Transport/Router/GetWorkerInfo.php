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
use Temporal\Client\DataConverter\DataConverterInterface;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Internal\ServiceContainer;
use Temporal\Client\Worker\TaskQueueInterface;

final class GetWorkerInfo extends Route
{
    /**
     * @var RepositoryInterface<TaskQueueInterface>
     */
    private RepositoryInterface $queues;

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * @param RepositoryInterface $queues
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(RepositoryInterface $queues, DataConverterInterface $dataConverter)
    {
        $this->queues = $queues;
        $this->dataConverter = $dataConverter;
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

        $resolver->resolve($this->dataConverter->toPayloads($result));
    }

    /**
     * @param TaskQueueInterface $taskQueue
     * @return array
     */
    private function workerToArray(TaskQueueInterface $taskQueue): array
    {
        return [
            'TaskQueue' => $taskQueue->getId(),
            'Workflows' => $this->map($taskQueue->getWorkflows(), function (WorkflowPrototype $workflow) {
                return [
                    'Name' => $workflow->getId(),
                    'Queries' => $this->keys($workflow->getQueryHandlers()),
                    'Signals' => $this->keys($workflow->getSignalHandlers()),
                ];
            }),
            'Activities' => $this->map($taskQueue->getActivities(), function (ActivityPrototype $activity) {
                return [
                    'Name' => $activity->getId(),
                ];
            })
            ,
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
