<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Transport\TransportInterface;
use Temporal\Client\Worker\Workflow\Pool;

/**
 * @internal Worker is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Worker
 */
abstract class Worker implements WorkerInterface
{
    /**
     * @psalm-var array<array-key, callable(\Throwable): void>
     * @var array|callable[]
     */
    private array $errors = [];

    /**
     * @var array
     */
    private array $ticks = [];

    /**
     * @var ReaderInterface
     */
    protected ReaderInterface $reader;

    /**
     * @var TransportInterface
     */
    protected TransportInterface $transport;

    /**
     * @var Pool
     */
    protected Pool $pool;

    /**
     * @param ReaderInterface $reader
     * @param TransportInterface $transport
     */
    public function __construct(ReaderInterface $reader, TransportInterface $transport)
    {
        $this->reader = $reader;
        $this->transport = $transport;
        $this->pool = new Pool();
    }

    /**
     * @return ReaderInterface
     */
    protected function getMetadataReader(): ReaderInterface
    {
        return $this->reader;
    }

    /**
     * @param callable $then
     * @return $this
     */
    public function onError(callable $then): self
    {
        $this->errors[] = $then;

        return $this;
    }

    /**
     * @param callable $then
     * @return $this
     */
    public function onTick(callable $then): WorkerInterface
    {
        $this->ticks[] = $then;

        return $this;
    }

    /**
     * @return void
     */
    protected function tick(): void
    {
        foreach ($this->ticks as $tick) {
            $tick();
        }
    }

    /**
     * @param \Throwable $e
     * @throws \Throwable
     */
    protected function throw(\Throwable $e): void
    {
        if (($depth = \func_get_args()[1] ?? 0) > 2) {
            return;
        }

        if (\count($this->errors) === 0) {
            throw $e;
        }

        foreach ($this->errors as $handler) {
            try {
                $handler($e);
            } catch (\Throwable $e) {
                $this->throw($e, $depth + 1);
            }
        }
    }
}
