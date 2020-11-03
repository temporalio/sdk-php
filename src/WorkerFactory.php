<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Evenement\EventEmitterTrait;
use React\Promise\PromiseInterface;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Temporal\Client\Meta\ReaderAwareInterface;
use Temporal\Client\Meta\ReaderAwareTrait;
use Temporal\Client\Transport\Client;
use Temporal\Client\Transport\Protocol\Command\RequestInterface;
use Temporal\Client\Transport\Protocol\Protocol;
use Temporal\Client\Transport\Queue\SplQueue;
use Temporal\Client\Transport\Router;
use Temporal\Client\Transport\RouterInterface;
use Temporal\Client\Transport\Server;
use Temporal\Client\Worker\Env\EnvironmentInterface;
use Temporal\Client\Worker\Env\RoadRunner as RoadRunnerEnvironment;
use Temporal\Client\Worker\Event;
use Temporal\Client\Worker\FactoryInterface;
use Temporal\Client\Worker\Loop;
use Temporal\Client\Worker\Pool;
use Temporal\Client\Worker\PoolInterface;
use Temporal\Client\Worker\Worker;
use Temporal\Client\Worker\WorkerInterface;

/**
 * @noinspection PhpSuperClassIncompatibleWithInterfaceInspection
 */

final class WorkerFactory implements FactoryInterface, ReaderAwareInterface
{
    use EventEmitterTrait;
    use ReaderAwareTrait;

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
    private const ERROR_WORKER_NOT_FOUND = 'Cannot find a worker for task queue "%s"';

    /**
     * @var string
     */
    private const CTX_TASK_QUEUE = 'taskQueue';

    /**
     * Blocking RoadRunner worker.
     *
     * Its task is to receive a request for incoming messages and wait
     * for this request to be completed on the server side.
     *
     * In response, a message and headers should come in string format.
     *
     * @var RoadRunnerWorker
     */
    private RoadRunnerWorker $rr;

    /**
     * The collection (pool) of workers in this process.
     *
     * The task of the pool of workers is to be able to register a
     * new worker and return it, if necessary, by the queue
     * identifier ({@see WorkerFactory::CTX_TASK_QUEUE}).
     *
     * @var PoolInterface
     */
    private PoolInterface $workers;

    /**
     * Worker environment.
     *
     * The task of the environment is to specify the set of queries to process
     * in the case that only activity or workflow processing is required.
     *
     * @var EnvironmentInterface
     */
    private EnvironmentInterface $env;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var SplQueue
     */
    private SplQueue $commands;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var Server
     */
    private Server $server;

    /**
     * @var Protocol
     */
    private Protocol $protocol;

    /**
     * @param RoadRunnerWorker $worker
     * @param EnvironmentInterface|null $env
     * @throws \Exception
     */
    public function __construct(RoadRunnerWorker $worker, EnvironmentInterface $env = null)
    {
        $this->rr = $worker;
        $this->env = $env ?? new RoadRunnerEnvironment();

        $this->initialize();
        $this->boot();
    }

    /**
     * @return void
     */
    private function initialize(): void
    {
        $this->workers = new Pool();
        $this->commands = new SplQueue();
        $this->protocol = new Protocol();
        $this->router = new Router();
        $this->client = new Client($this->commands);
        $this->server = new Server($this->commands, function (RequestInterface $request, array $headers) {
            // When the event of an incoming message fires
            // then we call global routes first.
            return $this->dispatchRouterOr($request, $headers,
                // Otherwise, we try to find the context of a specific worker
                // and redirect the request there.
                fn() => $this->dispatchWorker($request, $headers)
            );
        });
    }

    /**
     * @param RequestInterface $request
     * @param array $headers
     * @param \Closure $or
     * @return PromiseInterface
     */
    private function dispatchRouterOr(RequestInterface $request, array $headers, \Closure $or): PromiseInterface
    {
        if (! isset($headers[self::CTX_TASK_QUEUE])) {
            return $this->router->dispatch($request, $headers);
        }

        return $or($request, $headers);
    }

    /**
     * @param RequestInterface $request
     * @param array $headers
     * @return PromiseInterface
     */
    private function dispatchWorker(RequestInterface $request, array $headers): PromiseInterface
    {
        $taskQueue = $this->getTaskQueueOrFail($headers);

        $worker = $this->workers->find($taskQueue);

        if ($worker === null) {
            throw new \OutOfRangeException(\sprintf(self::ERROR_WORKER_NOT_FOUND, $taskQueue));
        }

        return $worker->dispatch($request, $headers);
    }

    /**
     * @param array $headers
     * @return string
     */
    private function getTaskQueueOrFail(array $headers): string
    {
        $taskQueue = $headers[self::CTX_TASK_QUEUE];

        if (! \is_string($taskQueue)) {
            $error = \sprintf(self::ERROR_HEADER_NOT_STRING_TYPE, self::CTX_TASK_QUEUE, \get_debug_type($taskQueue));
            throw new \InvalidArgumentException($error);
        }

        return $taskQueue;
    }

    /**
     * @return void
     */
    private function boot(): void
    {
        $this->on(Event::ON_PROCEED, function () {
            Loop::next();
        });

        // Boot routes
        $this->router->add(new Router\GetWorkerInfo($this->workers));
    }

    /**
     * @param string $taskQueue
     * @return WorkerInterface
     * @throws \Exception
     */
    public function create(string $taskQueue = self::DEFAULT_TASK_QUEUE): WorkerInterface
    {
        $worker = new Worker($this->client, $this->getReader(), $this->env, $taskQueue);

        $this->workers->add($worker);

        return $worker;
    }

    /**
     * @return int
     */
    public function start(): int
    {
        while ($message = $this->rr->receive($headers)) {
            try {
                $this->assertHeadersType($headers);
                $this->assertMessageType($message);

                $response = $this->process($message, $headers);

                $this->rr->send($response);
            } catch (\Throwable $e) {
                $this->rr->error((string)$e);
            }
        }

        return 0;
    }

    /**
     * @param mixed $headers
     */
    private function assertHeadersType($headers): void
    {
        if (! \is_string($headers)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_HEADERS_TYPE, \get_debug_type($headers)));
        }
    }

    /**
     * @param mixed $message
     */
    private function assertMessageType($message): void
    {
        if (! \is_string($message)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_MESSAGE_TYPE, \get_debug_type($message)));
        }
    }

    /**
     * @param string $request
     * @param string $context
     * @return string
     * @throws \Throwable
     */
    private function process(string $message, string $context): string
    {
        $headers = $this->protocol->decodeHeaders($context);
        $commands = $this->protocol->decodeCommands($message);

        $this->emit(Event::ON_RECEIVED, [$commands, $headers]);

        foreach ($commands as $command) {
            if ($command instanceof RequestInterface) {
                $this->server->dispatch($command, $headers);
            } else {
                $this->client->dispatch($command);
            }
        }

        $this->emit(Event::ON_SIGNAL, [$this->commands, $headers]);
        $this->emit(Event::ON_CALLBACK, [$this->commands, $headers]);
        $this->emit(Event::ON_TICK, [$this->commands, $headers]);
        $this->emit(Event::ON_PROCEED, [$this->commands, $headers]);

        return $this->protocol->encode($this->commands);
    }
}
