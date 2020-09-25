<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Worker\ExecutorInterface;

/**
 * @psalm-type RestartTimes = RestartableWorker::INFINITE | int
 */
class RestartableExecutor implements ExecutorInterface
{
    /**
     * @var int
     */
    private const EXIT_OK = 0x00;

    /**
     * @var int
     */
    private const EXIT_FAIL = -0x01;

    /**
     * @var float
     */
    private const INFINITE = \INF;

    /**
     * @var ExecutorInterface
     */
    private ExecutorInterface $worker;

    /**
     * @var int
     */
    private int $microseconds = 0;

    /**
     * @psalm-var RestartTimes
     *
     * @var float
     */
    private float $times = \INF;

    /**
     * @param ExecutorInterface $worker
     */
    public function __construct(ExecutorInterface $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @param ExecutorInterface $worker
     * @return static
     */
    public static function new(ExecutorInterface $worker): self
    {
        return new static($worker);
    }

    /**
     * @param float $seconds
     * @return $this
     */
    public function waitForRestart(float $seconds): self
    {
        $this->microseconds = (int)($seconds * 1000000);

        return $this;
    }

    /**
     * @psalm-param RestartTimes $times
     * @param float $times
     * @return $this
     */
    public function times($times = self::INFINITE): self
    {
        \assert($times >= 0 && \is_int($times) || $times === self::INFINITE);

        $this->times = $times;

        return $this;
    }

    /**
     * @param string $name
     * @return int
     */
    public function run(string $name = self::DEFAULT_WORKER_ID): int
    {
        [$exit, $iteration] = [self::EXIT_OK, 1];

        while (true) {
            try {
                $this->worker->run($name);
            } catch (\Throwable $e) {
                $exit = $e->getCode();
            }

            if (++$iteration > $this->times && $this->times !== self::INFINITE) {
                 break;
            }

            \usleep($this->microseconds);
        }

        return $exit ?: self::EXIT_FAIL;
    }
}
