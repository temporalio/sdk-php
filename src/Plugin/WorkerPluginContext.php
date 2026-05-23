<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Worker\WorkerOptions;

/**
 * Builder-style configuration context for worker plugins.
 *
 * Plugins modify this builder in {@see WorkerPluginInterface::configureWorker()}.
 */
final class WorkerPluginContext
{
    /** @var list<Interceptor> */
    private array $interceptors = [];

    public function __construct(
        private readonly string $taskQueue,
        private WorkerOptions $workerOptions,
        private ?ExceptionInterceptorInterface $exceptionInterceptor = null,
    ) {}

    public function getTaskQueue(): string
    {
        return $this->taskQueue;
    }

    public function getWorkerOptions(): WorkerOptions
    {
        return $this->workerOptions;
    }

    public function setWorkerOptions(WorkerOptions $workerOptions): self
    {
        $this->workerOptions = $workerOptions;
        return $this;
    }

    public function getExceptionInterceptor(): ?ExceptionInterceptorInterface
    {
        return $this->exceptionInterceptor;
    }

    public function setExceptionInterceptor(?ExceptionInterceptorInterface $exceptionInterceptor): self
    {
        $this->exceptionInterceptor = $exceptionInterceptor;
        return $this;
    }

    /**
     * @return list<Interceptor>
     */
    public function getInterceptors(): array
    {
        return $this->interceptors;
    }

    /**
     * @param list<Interceptor> $interceptors
     */
    public function setInterceptors(array $interceptors): self
    {
        $this->interceptors = $interceptors;
        return $this;
    }

    /**
     * Add an interceptor to the worker pipeline.
     */
    public function addInterceptor(Interceptor $interceptor): self
    {
        $this->interceptors[] = $interceptor;
        return $this;
    }
}
