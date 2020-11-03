<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use React\Promise\PromiseInterface;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Temporal\Client\Meta\ReaderAwareInterface;
use Temporal\Client\Meta\ReaderAwareTrait;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Json;
use Temporal\Client\Protocol\Protocol;
use Temporal\Client\Protocol\ProtocolInterface;
use Temporal\Client\Protocol\Router;
use Temporal\Client\Protocol\RouterInterface;
use Temporal\Client\Worker\Env\EnvironmentInterface;
use Temporal\Client\Worker\Env\RoadRunner as RoadRunnerEnvironment;
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
    use ReaderAwareTrait;

    /**
     * @var string
     */
    private const CTX_TASK_QUEUE = 'taskQueue';

    /**
     * @var RoadRunnerWorker
     */
    private RoadRunnerWorker $rr;

    /**
     * @var PoolInterface|WorkerInterface[]
     */
    private PoolInterface $workers;

    /**
     * @var EnvironmentInterface
     */
    private EnvironmentInterface $env;

    /**
     * @var ProtocolInterface
     */
    private ProtocolInterface $protocol;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @param RoadRunnerWorker          $worker
     * @param EnvironmentInterface|null $env
     * @throws \Exception
     */
    public function __construct(RoadRunnerWorker $worker, EnvironmentInterface $env = null)
    {
        $this->workers = new Pool();

        $this->rr = $worker;
        $this->env = $env ?? new RoadRunnerEnvironment();

        $this->router = new Router();
        $this->router->add(new Router\GetWorkerInfo($this->workers));

        $this->protocol = new Protocol(
            \Closure::fromCallable([$this, 'dispatch'])
        );
    }

    /**
     * @param RequestInterface $request
     * @param array            $headers
     * @return PromiseInterface
     */
    private function dispatch(RequestInterface $request, array $headers): PromiseInterface
    {
        if (!isset($headers[self::CTX_TASK_QUEUE])) {
            return $this->router->dispatch($request, $headers);
        }

        $taskQueue = $headers[self::CTX_TASK_QUEUE];

        if (!\is_string($taskQueue)) {
            throw new \InvalidArgumentException(
                \vsprintf('Header "%s" argument type must be a string, but %s given', [
                    self::CTX_TASK_QUEUE,
                    \get_debug_type($taskQueue),
                ])
            );
        }

        $worker = $this->workers->find($taskQueue);

        if ($worker === null) {
            throw new \OutOfRangeException(\sprintf('Cannot find a worker for task queue "%s"', $taskQueue));
        }

        return $worker->dispatch($request, $headers);
    }

    /**
     * @param string $taskQueue
     * @return WorkerInterface
     * @throws \Exception
     */
    public function create(string $taskQueue = self::DEFAULT_TASK_QUEUE): WorkerInterface
    {
        $worker = new Worker($this->protocol, $this->getReader(), $this->env, $taskQueue);

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
                if (!\is_string($message) || !\is_string($headers)) {
                    throw new \RuntimeException('Invalid received message type');
                }

                $response = $this->protocol->next($message, $this->parseHeaders($headers), static function () {
                    Loop::next();
                });

                $this->rr->send($response);
            } catch (\Throwable $e) {
                $this->rr->error((string) $e);
            }
        }

        return 0;
    }

    /**
     * @param string $headers
     * @return array
     * @throws \JsonException
     */
    private function parseHeaders(string $headers): array
    {
        $result = Json::decode($headers, \JSON_OBJECT_AS_ARRAY);

        if ($result !== null && !\is_array($result)) {
            throw new \LogicException('Invalid context format');
        }

        return $result ?? [];
    }
}
