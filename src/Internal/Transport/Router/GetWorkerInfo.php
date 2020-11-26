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
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Worker\PoolInterface;
use Temporal\Client\Worker\WorkerInterface;

final class GetWorkerInfo extends Route
{
    /**
     * @var PoolInterface
     */
    private PoolInterface $workers;

    /**
     * @param PoolInterface $workers
     */
    public function __construct(PoolInterface $workers)
    {
        $this->workers = $workers;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $result = [];

        foreach ($this->workers as $worker) {
            $result[] = $this->workerToArray($worker);
        }

        $resolver->resolve($result);
    }

    /**
     * @param WorkerInterface $worker
     * @return array
     */
    private function workerToArray(WorkerInterface $worker): array
    {
        return [
            'taskQueue'  => $worker->getTaskQueue(),
            'workflows'  => $this->map($worker->getWorkflows(), function (WorkflowPrototype $workflow) {
                return [
                    'name'    => $workflow->getName(),
                    'queries' => $this->keys($workflow->getQueryHandlers()),
                    'signals' => $this->keys($workflow->getSignalHandlers()),
                ];
            }),
            'activities' => $this->map($worker->getActivities(), function (ActivityPrototype $activity) {
                return [
                    'name' => $activity->getName(),
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
