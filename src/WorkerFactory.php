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
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
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
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Codec\CodecInterface;
use Temporal\Worker\Transport\Codec\JsonCodec;
use Temporal\Worker\Transport\Codec\ProtoCodec;
use Temporal\Worker\Transport\Command\ResponseInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Worker\Worker;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
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
    protected DataConverterInterface $converter;

    /**
     * @var ReaderInterface
     */
    protected ReaderInterface $reader;

    /**
     * @var RouterInterface
     */
    protected RouterInterface $router;

    /**
     * @var RepositoryInterface<WorkerInterface>
     */
    protected RepositoryInterface $queues;

    /**
     * @var CodecInterface
     */
    protected CodecInterface $codec;

    /**
     * @var ClientInterface
     */
    protected ClientInterface $client;

    /**
     * @var ServerInterface
     */
    protected ServerInterface $server;

    /**
     * @var QueueInterface
     */
    protected QueueInterface $responses;

    /**
     * @var RPCConnectionInterface
     */
    protected RPCConnectionInterface $rpc;

    /**
     * @var MarshallerInterface<array>
     */
    protected MarshallerInterface $marshaller;

    /**
     * @var EnvironmentInterface
     */
    protected EnvironmentInterface $env;

    /**
     * @param DataConverterInterface $dataConverter
     * @param RPCConnectionInterface $rpc
     */
    public function __construct(
        DataConverterInterface $dataConverter,
        RPCConnectionInterface $rpc,
    ) {
        $this->converter = $dataConverter;
        $this->rpc = $rpc;

        $this->boot();
    }

    /**
     * @param DataConverterInterface|null $converter
     * @param RPCConnectionInterface|null $rpc
     *
     * @return WorkerFactoryInterface
     */
    public static function create(
        DataConverterInterface $converter = null,
        RPCConnectionInterface $rpc = null,
    ): WorkerFactoryInterface {
        return new static(
            $converter ?? DataConverter::createDefault(),
            $rpc ?? Goridge::create(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newWorker(
        string $taskQueue = self::DEFAULT_TASK_QUEUE,
        WorkerOptions $options = null,
        ExceptionInterceptorInterface $exceptionInterceptor = null,
        PipelineProvider $interceptorProvider = null,
    ): WorkerInterface {
        $worker = new Worker(
            $taskQueue,
            $options ?? WorkerOptions::new(),
            ServiceContainer::fromWorkerFactory(
                $this,
                $exceptionInterceptor ?? ExceptionInterceptor::createDefault(),
                $interceptorProvider ?? new SimplePipelineProvider(),
            ),
            $this->rpc,
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
     * @return MarshallerInterface<array>
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
        $this->emit(LoopInterface::ON_FINALLY);
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
    protected function createTaskQueue(): RepositoryInterface
    {
        return new ArrayRepository();
    }

    /**
     * @return RouterInterface
     */
    protected function createRouter(): RouterInterface
    {
        $router = new Router();
        $router->add(new Router\GetWorkerInfo($this->queues, $this->marshaller));

        return $router;
    }

    /**
     * @return QueueInterface
     */
    protected function createQueue(): QueueInterface
    {
        return new ArrayQueue();
    }

    /**
     * @return ClientInterface
     */
    #[Pure]
    protected function createClient(): ClientInterface
    {
        return new Client($this->responses);
    }

    /**
     * @return ServerInterface
     */
    protected function createServer(): ServerInterface
    {
        return new Server($this->responses, $this->onRequest(...));
    }

    /**
     * @param ReaderInterface $reader
     * @return MarshallerInterface<array>
     */
    protected function createMarshaller(ReaderInterface $reader): MarshallerInterface
    {
        return new Marshaller(new AttributeMapperFactory($reader));
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
            if ($command instanceof ResponseInterface) {
                $this->client->dispatch($command);
                continue;
            }
            $this->server->dispatch($command, $headers);
        }

        $this->tick();

        return $this->codec->encode($this->responses);
    }

    /**
     * @param ServerRequestInterface $request
     * @param array $headers
     * @return PromiseInterface
     */
    private function onRequest(ServerRequestInterface $request, array $headers): PromiseInterface
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
