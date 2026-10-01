<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\Internal\ServiceContainer;
use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;
use Temporal\Worker\Worker;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

final class CoreWorkerFactory extends \Temporal\Worker\Core\CoreWorkerFactory
{
    protected function createWorker(string $taskQueue, WorkerOptions $options, ServiceContainer $services): WorkerInterface
    {
        return new WorkerMock(
            new Worker($taskQueue, $options, $services, $this->rpc),
            RoadRunnerActivityInvocationCache::create($this->converter),
        );
    }
}
