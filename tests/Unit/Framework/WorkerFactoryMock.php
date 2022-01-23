<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework;

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
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\Internal\Transport\Server;
use Temporal\Internal\Transport\ServerInterface;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Codec\CodecInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

/**
 * @internal
 */
class WorkerFactoryMock implements WorkerFactoryInterface, LoopInterface
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

    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->converter = $dataConverter;

        $this->boot();
    }

    public static function create(): WorkerFactoryInterface
    {
        return new static(DataConverter::createDefault());
    }

    /**
     * {@inheritDoc}
     */
    public function newWorker(
        string $taskQueue = self::DEFAULT_TASK_QUEUE,
        WorkerOptions $options = null,
        ExceptionInterceptorInterface $exceptionInterceptor = null
    ): WorkerInterface {
        $worker = new WorkerMock(
            $taskQueue,
            $options ?? WorkerOptions::new(),
            ServiceContainer::fromWorkerFactory(
                $this,
                $exceptionInterceptor ?? ExceptionInterceptor::createDefault()
            ),

        );
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

    public function run(WorkerMock $worker = null): int
    {
        /** @var WorkerMock */
        $worker ??= self::newWorker();
        while ($batch = $worker->waitBatch()) {
            try {
                $worker->send($this->dispatch($batch));
            } catch (\Throwable $e) {
                $worker->error($e);
            }
        }
        $worker->complete();

        return 0;
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
        $this->client = new ClientMock($this->responses);
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

    private function dispatch(CommandBatchMock $commandBatch): QueueInterface
    {
        $this->env->update($commandBatch->context);

        foreach ($commandBatch->commands as $command) {
            if ($command instanceof RequestInterface) {
                $this->server->dispatch($command, $commandBatch->context);
            } else {
                $this->client->dispatch($command);
            }
        }

        $this->tick();

        return $this->responses;
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
