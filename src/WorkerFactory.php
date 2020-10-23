<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Temporal\Client\Meta\ReaderAwareInterface;
use Temporal\Client\Meta\ReaderAwareTrait;
use Temporal\Client\Protocol\Json;
use Temporal\Client\Worker\Env\EnvironmentInterface;
use Temporal\Client\Worker\Env\RoadRunner as RoadRunnerEnvironment;
use Temporal\Client\Worker\FactoryInterface;
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
    private const CTX_TASK_QUEUE = 'TaskQueue';

    /**
     * @var RoadRunnerWorker
     */
    private RoadRunnerWorker $rr;

    /**
     * @var array|WorkerInterface[]
     */
    private array $workers = [];

    /**
     * @var EnvironmentInterface
     */
    private EnvironmentInterface $env;

    /**
     * @param RoadRunnerWorker $worker
     * @param EnvironmentInterface|null $env
     */
    public function __construct(RoadRunnerWorker $worker, EnvironmentInterface $env = null)
    {
        $this->rr = $worker;
        $this->env = $env ?? new RoadRunnerEnvironment();
    }

    /**
     * @param string $taskQueue
     * @return WorkerInterface
     * @throws \Exception
     */
    public function create(string $taskQueue = self::DEFAULT_TASK_QUEUE): WorkerInterface
    {
        return $this->workers[$taskQueue] = new Worker($taskQueue, $this->getReader(), $this->env);
    }

    /**
     * @return int
     */
    public function start(): int
    {
        $context = null;

        while ($message = $this->rr->receive($context)) {
            try {
                if (! \is_string($message) || ! \is_string($context)) {
                    throw new \RuntimeException('Invalid received message type');
                }

                $response = $this->emit($message, $this->parseContext($context));

                $this->rr->send($response);
            } catch (\Throwable $e) {
                $this->rr->error((string)$e);
            }
        }

        return 0;
    }

    /**
     * @param string $message
     * @param array $context
     * @return string
     * @throws \JsonException
     */
    private function emit(string $message, array $context = []): string
    {
        $taskQueue = $context[self::CTX_TASK_QUEUE] ?? null;

        if ($taskQueue === null) {
            return Json::encode($this->getGetWorkerInfoResponse());
        }

        $worker = $this->getWorker($taskQueue);

        return $worker->emit($message, $context);
    }

    /**
     * @return array
     */
    private function getGetWorkerInfoResponse(): array
    {
        return \array_map(static fn(WorkerInterface $worker): array => $worker->toArray(), $this->workers);
    }

    /**
     * @param string $taskQueue
     * @return WorkerInterface
     */
    private function getWorker(string $taskQueue): WorkerInterface
    {
        if (! isset($this->workers[$taskQueue])) {
            $error = \sprintf('Cannot find a worker for task queue "%s"', $taskQueue);
            throw new \RuntimeException($error);
        }

        /** @var WorkerInterface $worker */
        return $this->workers[$taskQueue];
    }

    /**
     * @param string $context
     * @return array
     * @throws \JsonException
     */
    private function parseContext(string $context): array
    {
        $result = Json::decode($context, \JSON_OBJECT_AS_ARRAY);

        if ($result !== null && ! \is_array($result)) {
            throw new \LogicException('Invalid context format');
        }

        return $result ?? [];
    }
}
