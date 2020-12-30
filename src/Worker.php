<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineReader;
use Doctrine\Common\Annotations\Reader;
use JetBrains\PhpStorm\Pure;
use React\Promise\PromiseInterface;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Spiral\Goridge\Relay;
use Temporal\Client\Internal\Codec\CodecInterface;
use Temporal\Client\Internal\Codec\JsonCodec;
use Temporal\Client\Internal\Codec\MsgpackCodec;
use Temporal\Client\Internal\DataConverter\DataConverter;
use Temporal\Client\Internal\DataConverter\JsonConverter;
use Temporal\Client\Internal\DataConverter\ScalarJsonConverter;
use Temporal\Client\Internal\Events\EventEmitterTrait;
use Temporal\Client\Internal\Queue\ArrayQueue;
use Temporal\Client\Internal\Queue\QueueInterface;
use Temporal\Client\Internal\Repository\ArrayRepository;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Internal\Transport\CapturedClientInterface;
use Temporal\Client\Internal\Transport\Client;
use Temporal\Client\Internal\Transport\ClientInterface;
use Temporal\Client\Internal\Transport\Router;
use Temporal\Client\Internal\Transport\RouterInterface;
use Temporal\Client\Internal\Transport\Server;
use Temporal\Client\Internal\Transport\ServerInterface;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Worker\FactoryInterface;
use Temporal\Client\Worker\LoopInterface;
use Temporal\Client\Worker\TaskQueue;
use Temporal\Client\Worker\TaskQueueInterface;
use Temporal\Client\Worker\Transport\Goridge;
use Temporal\Client\Worker\Transport\RelayConnectionInterface;
use Temporal\Client\Worker\Transport\RoadRunner;
use Temporal\Client\Worker\Transport\RpcConnectionInterface;

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
     * @param RelayConnectionInterface|null $relay
     * @param RpcConnectionInterface|null $rpc
     */
    public function __construct(RelayConnectionInterface $relay = null, RpcConnectionInterface $rpc = null)
    {
        // todo: remove defaults here
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
    #[Pure]
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
        $router->add(new Router\GetWorkerInfo(
            $this->queues,
            new DataConverter(new ScalarJsonConverter())
        ));

        return $router;
    }

    /**
     * @return CodecInterface
     */
    private function createCodec(): CodecInterface
    {
        return new JsonCodec();
    }

    /**
     * @return QueueInterface
     */
    private function createQueue(): QueueInterface
    {
        return new ArrayQueue();
    }

    /**
     * @return CapturedClientInterface
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
