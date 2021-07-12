<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use Doctrine\Common\Annotations\Reader;
use JetBrains\PhpStorm\Pure;
use React\Promise\PromiseInterface;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\Transport\Codec\CodecInterface;
use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Internal\Queue\ArrayQueue;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Repository\ArrayRepository;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\Transport\Client;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\Internal\Transport\Server;
use Temporal\Internal\Transport\ServerInterface;
use Temporal\Worker\Transport\Codec\JsonCodec;
use Temporal\Worker\Transport\Codec\ProtoCodec;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Worker;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Worker\WorkerOptions;

/**
 * WorkerFactory is primary entry point for the temporal application. This class is responsible for the communication
 * with parent RoadRunner process and can be used to create taskQueue workflow and activity workers.
 *
 * <code>
 * $factory = WorkerFactory::create();
 *
 * $worker = $factory->newWorker('default');
 *
 * $worker->registerWorkflowTypes(WorkflowType::class);
 * $worker->registerActivityImplementations(new MyActivityImplementation());
 *
 * </code>
 */
class WorkerFactory implements WorkerFactoryInterface, LoopInterface
{
    use EventEmitterTrait;

    /**
     * @var string
     */
    private const ERROR_MESSAGE_TYPE = 'Received message type must be a string, but %s given';

    /**
     * @var string
     */
    private const ERROR_HEADERS_TYPE = 'Received headers type must be a string, but %s given';

    /**
     * @var string
     */
    private const ERROR_HEADER_NOT_STRING_TYPE = 'Header "%s" argument type must be a string, but %s given';

    /**
     * @var string
     */
    private const ERROR_QUEUE_NOT_FOUND = 'Cannot find a worker for task queue "%s"';

    /**
     * @var string
     */
    private const HEADER_TASK_QUEUE = 'taskQueue';

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $converter;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var RepositoryInterface<WorkerInterface>
     */
    private RepositoryInterface $queues;

    /**
     * @var CodecInterface
     */
    private CodecInterface $codec;

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var ServerInterface
     */
    private ServerInterface $server;

    /**
     * @var QueueInterface
     */
    private QueueInterface $responses;

    /**
     * @var RPCConnectionInterface
     */
    private RPCConnectionInterface $rpc;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @var EnvironmentInterface
     */
    private EnvironmentInterface $env;

    /**
     * @param DataConverterInterface $dataConverter
     * @param RPCConnectionInterface $rpc
     */
    public function __construct(DataConverterInterface $dataConverter, RPCConnectionInterface $rpc)
    {
        $this->converter = $dataConverter;
        $this->rpc = $rpc;

        $this->boot();
    }

    /**
     * @param DataConverterInterface|null $converter
     * @param RPCConnectionInterface|null $rpc
     * @return WorkerFactoryInterface
     */
    public static function create(
        DataConverterInterface $converter = null,
        RPCConnectionInterface $rpc = null
    ): WorkerFactoryInterface {
        return new static(
            $converter ?? DataConverter::createDefault(),
            $rpc ?? Goridge::create()
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
        $worker = new Worker(
            $taskQueue,
            $options ?? WorkerOptions::new(),
            ServiceContainer::fromWorkerFactory(
                $this,
                $exceptionInterceptor ?? ExceptionInterceptor::createDefault()
            ),
            $this->rpc
        );
        $this->queues->add($worker);

        return $worker;
    }

    /**
     * @return ReaderInterface
     */
    public function getReader(): ReaderInterface
    {
        return $this->reader;
    }

    /**
     * @return ClientInterface
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * @return QueueInterface
     */
    public function getQueue(): QueueInterface
    {
        return $this->responses;
    }

    /**
     * @return DataConverterInterface
     */
    public function getDataConverter(): DataConverterInterface
    {
        return $this->converter;
    }

    /**
     * @return MarshallerInterface
     */
    public function getMarshaller(): MarshallerInterface
    {
        return $this->marshaller;
    }

    /**
     * @return EnvironmentInterface
     */
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
     * @return void
     */
    public function tick(): void
    {
        $this->emit(LoopInterface::ON_SIGNAL);
        $this->emit(LoopInterface::ON_CALLBACK);
        $this->emit(LoopInterface::ON_QUERY);
        $this->emit(LoopInterface::ON_TICK);
    }

    /**
     * @return void
     */
    private function boot(): void
    {
        $this->reader = $this->createReader();
        $this->marshaller = $this->createMarshaller($this->reader);
        $this->queues = $this->createTaskQueue();
        $this->router = $this->createRouter();
        $this->responses = $this->createQueue();
        $this->client = $this->createClient();
        $this->server = $this->createServer();
        $this->env = new Environment();
    }

    /**
     * @return ReaderInterface
     */
    protected function createReader(): ReaderInterface
    {
        if (\interface_exists(Reader::class)) {
            return new SelectiveReader([new AnnotationReader(), new AttributeReader()]);
        }

        return new AttributeReader();
    }

    /**
     * @return RepositoryInterface<WorkerInterface>
     */
    private function createTaskQueue(): RepositoryInterface
    {
        return new ArrayRepository();
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
     * @return QueueInterface
     */
    private function createQueue(): QueueInterface
    {
        return new ArrayQueue();
    }

    /**
     * @return ClientInterface
     */
    #[Pure]
    private function createClient(): ClientInterface
    {
        return new Client($this->responses, $this);
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
     * @return CodecInterface
     */
    private function createCodec(): CodecInterface
    {
        // todo: make it better
        switch ($_SERVER['RR_CODEC'] ?? null) {
            case 'protobuf':
                return new ProtoCodec($this->converter);
            default:
                return new JsonCodec($this->converter);
        }
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

    /**
     * @param RequestInterface $request
     * @param array $headers
     * @return PromiseInterface
     */
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

    /**
     * @param string $taskQueueName
     * @return WorkerInterface
     */
    private function findTaskQueueOrFail(string $taskQueueName): WorkerInterface
    {
        $queue = $this->queues->find($taskQueueName);

        if ($queue === null) {
            throw new \OutOfRangeException(\sprintf(self::ERROR_QUEUE_NOT_FOUND, $taskQueueName));
        }

        return $queue;
    }

    /**
     * @param array $headers
     * @return string
     */
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
