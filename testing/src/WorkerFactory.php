<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Logger;
use Temporal\Worker\ActivityInvocationCache\ActivityInvocationCacheInterface;
use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;
use Temporal\Worker\ServiceCredentials;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Worker\Worker;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

class WorkerFactory extends \Temporal\WorkerFactory
{
    private ActivityInvocationCacheInterface $activityCache;

    public function __construct(
        DataConverterInterface $dataConverter,
        RPCConnectionInterface $rpc,
        ActivityInvocationCacheInterface $activityCache,
        ?ServiceCredentials $credentials = null,
    ) {
        $this->activityCache = $activityCache;

        parent::__construct($dataConverter, $rpc, $credentials ?? ServiceCredentials::create());
    }

    public static function create(
        ?DataConverterInterface $converter = null,
        ?RPCConnectionInterface $rpc = null,
        ?ServiceCredentials $credentials = null,
        ?ActivityInvocationCacheInterface $activityCache = null,
    ): static {
        return new static(
            $converter ?? DataConverter::createDefault(),
            $rpc ?? Goridge::create(),
            $activityCache ?? RoadRunnerActivityInvocationCache::create($converter),
            $credentials,
        );
    }

    public function newWorker(
        string $taskQueue = self::DEFAULT_TASK_QUEUE,
        ?WorkerOptions $options = null,
        ?ExceptionInterceptorInterface $exceptionInterceptor = null,
        ?PipelineProvider $interceptorProvider = null,
        ?LoggerInterface $logger = null,
    ): WorkerInterface {
        $options ??= WorkerOptions::new();
        $worker = new WorkerMock(
            new Worker(
                $taskQueue,
                $options ?? WorkerOptions::new(),
                ServiceContainer::fromWorkerFactory(
                    $this,
                    $exceptionInterceptor ?? ExceptionInterceptor::createDefault(),
                    $interceptorProvider ?? new SimplePipelineProvider(),
                    new Logger(
                        $logger ?? new NullLogger(),
                        $options->enableLoggingInReplay,
                        $taskQueue,
                    ),
                ),
                $this->rpc,
            ),
            $this->activityCache,
        );
        $this->queues->add($worker);

        return $worker;
    }
}
