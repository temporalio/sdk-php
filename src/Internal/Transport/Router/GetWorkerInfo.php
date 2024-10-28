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
    private RepositoryInterface $queues;
    private MarshallerInterface $marshaller;

    public function __construct(RepositoryInterface $queues, MarshallerInterface $marshaller)
    {
        $this->queues = $queues;
        $this->marshaller = $marshaller;
    }

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $result = [];

        foreach ($this->queues as $taskQueue) {
            $result[] = $this->workerToArray($taskQueue);
        }

        $resolver->resolve(EncodedValues::fromValues($result));
    }

    private function workerToArray(WorkerInterface $worker): array
    {
        $workflowMap = static fn(WorkflowPrototype $workflow): array => [
            'Name'    => $workflow->getID(),
            'Queries' => \array_keys($workflow->getQueryHandlers()),
            'Signals' => \array_keys($workflow->getSignalHandlers()),
            // 'Updates' => $this->keys($workflow->getUpdateHandlers()),
        ];

        $activityMap = static fn(ActivityPrototype $activity): array => [
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
            'Flags' => (object) [],
        ];
    }

    private function map(iterable $items, \Closure $map): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $result[] = $map($value, $key);
        }

        return $result;
    }
}
