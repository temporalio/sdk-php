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
use Temporal\Common\SdkVersion;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\WorkerInterface;

final class GetWorkerInfo extends Route
{
    /**
     * @var RepositoryInterface
     */
    private RepositoryInterface $queues;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param RepositoryInterface $queues
     * @param MarshallerInterface $marshaller
     */
    public function __construct(RepositoryInterface $queues, MarshallerInterface $marshaller)
    {
        $this->queues = $queues;
        $this->marshaller = $marshaller;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $result = [];

        foreach ($this->queues as $taskQueue) {
            $result[] = $this->workerToArray($taskQueue);
        }

        $resolver->resolve(EncodedValues::fromValues($result));
    }

    /**
     * @param WorkerInterface $worker
     * @return array
     */
    private function workerToArray(WorkerInterface $worker): array
    {
        $workflowMap = function (WorkflowPrototype $workflow) {
            return [
                'Name'    => $workflow->getID(),
                'Queries' => $this->keys($workflow->getQueryHandlers()),
                'Signals' => $this->keys($workflow->getSignalHandlers()),
                // 'Updates' => $this->keys($workflow->getUpdateHandlers()),
            ];
        };

        $activityMap = static fn (ActivityPrototype $activity) => [
            'Name' => $activity->getID(),
        ];

        return [
            'TaskQueue'  => $worker->getID(),
            'Options'    => $this->marshaller->marshal($worker->getOptions()),
            // WorkflowInfo[]
            'Workflows'  => $this->map($worker->getWorkflows(), $workflowMap),
            // ActivityInfo[]
            'Activities' => $this->map($worker->getActivities(), $activityMap),
            'PhpSdkVersion' => SdkVersion::getSdkVersion(),
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
