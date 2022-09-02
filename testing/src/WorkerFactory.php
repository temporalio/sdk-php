<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Doctrine\Common\Annotations\Reader;
use React\Promise\PromiseInterface;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Queue\ArrayQueue;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Repository\ArrayRepository;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Client;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\Internal\Transport\Server;
use Temporal\Internal\Transport\ServerInterface;
use Temporal\Worker\ActivityInvocationCache\ActivityInvocationCacheInterface;
use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Codec\CodecInterface;
use Temporal\Worker\Transport\Codec\JsonCodec;
use Temporal\Worker\Transport\Codec\ProtoCodec;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Worker\Worker;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

class WorkerFactory implements WorkerFactoryInterface, LoopInterface
{
    use EventEmitterTrait;

    private const ERROR_HEADER_NOT_STRING_TYPE = 'Header "%s" argument type must be a string, but %s given';
    private const ERROR_QUEUE_NOT_FOUND = 'Cannot find a worker for task queue "%s"';
    private const HEADER_TASK_QUEUE = 'taskQueue';

    private DataConverterInterface $converter;
    private ReaderInterface $reader;
    private RouterInterface $router;

    /**
     * @var RepositoryInterface<WorkerInterface>
     */
    private RepositoryInterface $queues;
    private CodecInterface $codec;
    private ClientInterface $client;
    private ServerInterface $server;
    private QueueInterface $responses;
    private MarshallerInterface $marshaller;
    private EnvironmentInterface $env;
    private RPCConnectionInterface $rpc;
    private ActivityInvocationCacheInterface $activityCache;

    public function __construct(DataConverterInterface $dataConverter, RPCConnectionInterface $rpc, ActivityInvocationCacheInterface $activityCache)
    {
        $this->converter = $dataConverter;
        $this->rpc = $rpc;
        $this->activityCache = $activityCache;

        $this->boot();
    }

    public static function create(
        ?DataConverterInterface $dataConverter = null,
        ?RPCConnectionInterface $rpc = null,
        ?ActivityInvocationCacheInterface $activityCache = null
    ): WorkerFactoryInterface
    {
        return new static(
            $dataConverter ?? DataConverter::createDefault(),
            $rpc ?? Goridge::create(),
            $activityCache ?? RoadRunnerActivityInvocationCache::create($dataConverter)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newWorker(
        string $taskQueue = self::DEFAULT_TASK_QUEUE,
        WorkerOptions $options = null,
        ExceptionInterceptorInterface $exceptionInterceptor = null
    ): WorkerInterface {
        $worker = new WorkerMock(new Worker(
            $taskQueue,
            $options ?? WorkerOptions::new(),
            ServiceContainer::fromWorkerFactory(
                $this,
                $exceptionInterceptor ?? ExceptionInterceptor::createDefault()
            ),
            $this->rpc,
        ), $this->activityCache);
        $this->queues->add($worker);

        return $worker;
    }

    public function getReader(): ReaderInterface
    {
        return $this->reader;
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function getQueue(): QueueInterface
    {
        return $this->responses;
    }

    public function getDataConverter(): DataConverterInterface
    {
        return $this->converter;
    }

    public function getMarshaller(): MarshallerInterface
    {
        return $this->marshaller;
    }

    public function getEnvironment(): EnvironmentInterface
    {
        return $this->env;
    }

    /**
     * {@inheritDoc}
     */
    public function run(HostConnectionInterface $host = null): int
    {
        $host ??= RoadRunner::create();
        $this->codec = $this->createCodec();

        while ($msg = $host->waitBatch()) {
            try {
                $host->send($this->dispatch($msg->messages, $msg->context));
            } catch (\Throwable $e) {
                $host->error($e);
            }
        }

        return 0;
    }

    /**
     * @return CodecInterface
     */
    private function createCodec(): CodecInterface
    {
        switch ($_SERVER['RR_CODEC'] ?? null) {
            case 'proto':
            case 'protobuf':
                return new ProtoCodec($this->converter);
            default:
                return new JsonCodec($this->converter);
        }
    }

    public function tick(): void
    {
        $this->emit(LoopInterface::ON_SIGNAL);
        $this->emit(LoopInterface::ON_CALLBACK);
        $this->emit(LoopInterface::ON_QUERY);
        $this->emit(LoopInterface::ON_TICK);
    }

    private function boot(): void
    {
        $this->reader = $this->createReader();
        $this->marshaller = $this->createMarshaller($this->reader);
        $this->queues = new ArrayRepository();
        $this->router = $this->createRouter();
        $this->responses = new ArrayQueue();
        $this->client = new Client($this->responses, $this);
        $this->server = $this->createServer();
        $this->env = new Environment();
    }

    private function createReader(): ReaderInterface
    {
        if (\interface_exists(Reader::class)) {
            return new SelectiveReader([new AnnotationReader(), new AttributeReader()]);
        }

        return new AttributeReader();
    }

    /**
     * @return RouterInterface
     */
    private function createRouter(): RouterInterface
    {
        $router = new Router();
        $router->add(new Router\GetWorkerInfo($this->queues, $this->marshaller));

        return $router;
    }

    /**
     * @return ServerInterface
     */
    private function createServer(): ServerInterface
    {
        return new Server($this->responses, \Closure::fromCallable([$this, 'onRequest']));
    }

    /**
     * @param ReaderInterface $reader
     * @return MarshallerInterface
     */
    private function createMarshaller(ReaderInterface $reader): MarshallerInterface
    {
        return new Marshaller(new AttributeMapperFactory($reader));
    }

    /**
     * @param string $messages
     * @param array $headers
     * @return string
     */
    private function dispatch(string $messages, array $headers): string
    {
        $commands = $this->codec->decode($messages);
        $this->env->update($headers);

        foreach ($commands as $command) {
            if ($command instanceof RequestInterface) {
                $this->server->dispatch($command, $headers);
            } else {
                $this->client->dispatch($command);
            }
        }

        $this->tick();

        return $this->codec->encode($this->responses);
    }

    private function onRequest(RequestInterface $request, array $headers): PromiseInterface
    {
        if (!isset($headers[self::HEADER_TASK_QUEUE])) {
            return $this->router->dispatch($request, $headers);
        }

        $queue = $this->findTaskQueueOrFail(
            $this->findTaskQueueNameOrFail($headers)
        );

        return $queue->dispatch($request, $headers);
    }

    private function findTaskQueueOrFail(string $taskQueueName): WorkerInterface
    {
        $queue = $this->queues->find($taskQueueName);

        if ($queue === null) {
            throw new \OutOfRangeException(\sprintf(self::ERROR_QUEUE_NOT_FOUND, $taskQueueName));
        }

        return $queue;
    }

    private function findTaskQueueNameOrFail(array $headers): string
    {
        $taskQueue = $headers[self::HEADER_TASK_QUEUE];

        if (!\is_string($taskQueue)) {
            $error = \vsprintf(
                self::ERROR_HEADER_NOT_STRING_TYPE,
                [
                    self::HEADER_TASK_QUEUE,
                    \get_debug_type($taskQueue),
                ]
            );

            throw new \InvalidArgumentException($error);
        }

        return $taskQueue;
    }
}
