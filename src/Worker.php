<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineReader;
use Doctrine\Common\Annotations\Reader;
use JetBrains\PhpStorm\Pure;
use React\Promise\PromiseInterface;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Spiral\Goridge\Relay;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\Codec\CodecInterface;
use Temporal\Worker\Codec\JsonCodec;
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
use Temporal\Worker\Codec\MsgpackCodec;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Worker\FactoryInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\TaskQueue;
use Temporal\Worker\TaskQueueInterface;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\Transport\RelayConnectionInterface;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\Transport\RpcConnectionInterface;

final class Worker implements FactoryInterface
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
     * @var string
     */
    private const RESERVED_ANNOTATIONS = [
        'readonly',
    ];

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * @var RelayConnectionInterface
     */
    private RelayConnectionInterface $relay;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var RepositoryInterface<TaskQueueInterface>
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
     * @var RpcConnectionInterface
     */
    private RpcConnectionInterface $rpc;

    /**
     * @param DataConverterInterface|null $dataConverter
     * @param RelayConnectionInterface|null $relay
     * @param RpcConnectionInterface|null $rpc
     */
    public function __construct(
        DataConverterInterface $dataConverter = null,
        RelayConnectionInterface $relay = null,
        RpcConnectionInterface $rpc = null
    )
    {
        $this->dataConverter = $dataConverter ?? DataConverter::createDefault();

        $this->relay = $relay ?? new RoadRunner(Relay::create(Relay::PIPES));
        $this->rpc = $rpc ?? new Goridge(Relay::create('tcp://127.0.0.1:6001'));

        $this->boot();
    }

    /**
     * @return void
     */
    private function boot(): void
    {
        $this->reader = $this->createReader();
        $this->queues = $this->createTaskQueue();
        $this->router = $this->createRouter();
        $this->codec = $this->createCodec();
        $this->responses = $this->createQueue();
        $this->client = $this->createClient();
        $this->server = $this->createServer();
    }

    /**
     * @return ReaderInterface
     */
    private function createReader(): ReaderInterface
    {
        if (\interface_exists(Reader::class)) {
            foreach (self::RESERVED_ANNOTATIONS as $annotation) {
                DoctrineReader::addGlobalIgnoredName($annotation);
            }

            return new SelectiveReader([
                new AnnotationReader(),
                new AttributeReader(),
            ]);
        }

        return new AttributeReader();
    }

    /**
     * @return RepositoryInterface<TaskQueueInterface>
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
        $router->add(new Router\GetWorkerInfo($this->queues));

        return $router;
    }

    /**
     * @return CodecInterface
     */
    private function createCodec(): CodecInterface
    {
       // return new MsgpackCodec($this->dataConverter);
        return new JsonCodec($this->dataConverter);
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
     * {@inheritDoc}
     */
    public function createAndRegister(string $taskQueue = self::DEFAULT_TASK_QUEUE): TaskQueueInterface
    {
        $instance = $this->create($taskQueue);

        $this->register($instance);

        return $instance;
    }

    /**
     * @param string $taskQueue
     * @return TaskQueueInterface
     */
    public function create(string $taskQueue = self::DEFAULT_TASK_QUEUE): TaskQueueInterface
    {
        return new TaskQueue($taskQueue, $this, $this->rpc);
    }

    /**
     * {@inheritDoc}
     */
    public function register(TaskQueueInterface $queue): void
    {
        $this->queues->add($queue);
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
        return $this->dataConverter;
    }

    /**
     * {@inheritDoc}
     */
    public function run(): int
    {
        while ($msg = $this->relay->await()) {
            try {
                $this->relay->send($this->dispatch($msg->messages, $msg->context));
            } catch (\Throwable $e) {
                $this->relay->error($e);
            }
        }

        return 0;
    }

    /**
     * @param string $messages
     * @param array $headers
     * @return string
     */
    private function dispatch(string $messages, array $headers): string
    {
        $commands = $this->codec->decode($messages);

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
     * @return TaskQueueInterface
     */
    private function findTaskQueueOrFail(string $taskQueueName): TaskQueueInterface
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
            $error = \vsprintf(self::ERROR_HEADER_NOT_STRING_TYPE, [
                self::HEADER_TASK_QUEUE,
                \get_debug_type($taskQueue)
            ]);

            throw new \InvalidArgumentException($error);
        }

        return $taskQueue;
    }
}
