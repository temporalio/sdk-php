<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Feature;

use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InvokerInterface;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Logger\FanoutLogger;
use Temporal\Tests\Acceptance\App\Logger\LoggerFactory;
use Temporal\Tests\Acceptance\App\Logger\TranscriptAdapter;
use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

#[Singleton]
final class WorkerFactory
{
    public function __construct(
        private readonly WorkerFactoryInterface $workerFactory,
        private readonly InvokerInterface $invoker,
        private readonly ?TranscriptWriter $transcript = null,
        private readonly ?LoggerInterface $stderr = null,
    ) {}

    public function createWorker(
        Feature $feature,
    ): WorkerInterface {
        $attribute = self::findAttribute(
            ...\array_map(static fn(array $check): string => $check[0], $feature->checks),
            ...$feature->workflows,
            ...$feature->activities,
        );
        $options = $attribute?->options === null ? null : $this->invoker->invoke($attribute->options);
        $interceptorProvider = $attribute?->pipelineProvider === null
            ? null
            : $this->invoker->invoke($attribute->pipelineProvider);
        $logger = $attribute?->logger === null ? null : $this->invoker->invoke($attribute->logger);

        if ($attribute?->plugins !== null) {
            $this->workerFactory->getPluginRegistry()->merge($attribute->plugins);
        }

        return $this->workerFactory->newWorker(
            $feature->taskQueue,
            $options ?? WorkerOptions::new()->withMaxConcurrentActivityExecutionSize(10),
            interceptorProvider: $interceptorProvider,
            logger: $logger ?? $this->buildLoggerForFeature($feature),
        );
    }

    private function buildLoggerForFeature(Feature $feature): LoggerInterface
    {
        $serverLogger = LoggerFactory::createServerLogger($feature->taskQueue);
        if ($this->transcript === null || $this->stderr === null) {
            return $serverLogger;
        }
        return new FanoutLogger(
            $this->stderr,
            $serverLogger,
            new TranscriptAdapter($this->transcript, $this->stderr),
        );
    }

    /**
     * Find {@see Worker} attribute in the classes collection.
     * If more than one attribute is found, an exception is thrown.
     */
    public static function findAttribute(string ...$classes): ?Worker
    {
        $classes = \array_unique($classes);
        /** @var array<array{0: Worker, 1: class-string}> $found */
        $found = [];

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(Worker::class);
            foreach ($attributes as $attribute) {
                $found[] = [$attribute->newInstance(), $class];
            }
        }

        if (\count($found) > 1) {
            throw new \RuntimeException(
                'Multiple #[Worker] attributes found: ' . \implode(', ', \array_column($found, 1)),
            );
        }

        return $found[0][0] ?? null;
    }
}
