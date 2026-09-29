<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Psr\Log\LoggerInterface;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;
use Temporal\Worker\DispatcherInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

final class CoreWorkerFactory extends \Temporal\Worker\Core\CoreWorkerFactory
{
    public function newWorker(
        string $taskQueue = self::DEFAULT_TASK_QUEUE,
        ?WorkerOptions $options = null,
        ?ExceptionInterceptorInterface $exceptionInterceptor = null,
        ?PipelineProvider $interceptorProvider = null,
        ?LoggerInterface $logger = null,
    ): WorkerInterface {
        $worker = parent::newWorker($taskQueue, $options, $exceptionInterceptor, $interceptorProvider, $logger);
        \assert($worker instanceof DispatcherInterface);
        $worker = new WorkerMock($worker, RoadRunnerActivityInvocationCache::create($this->converter));
        $this->queues->add($worker, true);

        return $worker;
    }
}
