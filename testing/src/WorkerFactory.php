<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Logger;
use Temporal\Plugin\CompositePipelineProvider;
use Temporal\Plugin\PluginRegistry;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Plugin\WorkerPluginInterface;
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
        ?ServiceCredentials $credentials = null,
        ?PluginRegistry $pluginRegistry = null,
        ?WorkflowClient $client = null,
        ?ActivityInvocationCacheInterface $activityCache = null,
    ) {
        $this->activityCache = $activityCache ?? RoadRunnerActivityInvocationCache::create($dataConverter);

        parent::__construct($dataConverter, $rpc, $credentials ?? ServiceCredentials::create(), $pluginRegistry, $client);
    }

    /**
     * @psalm-suppress UnsafeInstantiation
     */
    public static function create(
        ?DataConverterInterface $converter = null,
        ?RPCConnectionInterface $rpc = null,
        ?ServiceCredentials $credentials = null,
        ?PluginRegistry $pluginRegistry = null,
        ?WorkflowClient $client = null,
        ?ActivityInvocationCacheInterface $activityCache = null,
    ): static {
        return new static(
            $converter ?? DataConverter::createDefault(),
            $rpc ?? Goridge::create(),
            $credentials,
            $pluginRegistry ?? new PluginRegistry(),
            $client,
            $activityCache,
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

        $workerContext = new WorkerPluginContext(
            taskQueue: $taskQueue,
            workerOptions: $options,
            exceptionInterceptor: $exceptionInterceptor,
        );
        $workerPlugins = $this->pluginRegistry->getPlugins(WorkerPluginInterface::class);
        /** @see WorkerPluginInterface::configureWorker() */
        Pipeline::prepare($workerPlugins)
            ->with(static fn() => null, 'configureWorker')($workerContext);

        $options = $workerContext->getWorkerOptions();

        // Merge plugin-contributed interceptors with user-provided ones
        $provider = new CompositePipelineProvider(
            $workerContext->getInterceptors(),
            $interceptorProvider ?? new SimplePipelineProvider(),
        );

        $worker = new WorkerMock(
            new Worker(
                $taskQueue,
                $options,
                ServiceContainer::fromWorkerFactory(
                    $this,
                    $workerContext->getExceptionInterceptor() ?? ExceptionInterceptor::createDefault(),
                    $provider,
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

        // Call initializeWorker hooks (forward order)
        /** @see WorkerPluginInterface::initializeWorker() */
        Pipeline::prepare($workerPlugins)
            ->with(static fn() => null, 'initializeWorker')($worker);

        $this->queues->add($worker);

        return $worker;
    }
}
