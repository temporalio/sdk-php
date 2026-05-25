<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Feature;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InvokerInterface;
use Temporal\Plugin\PluginInterface;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Logger\LoggerFactory;
use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;
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
            $registry = $this->workerFactory->getPluginRegistry();
            $registeredNames = \array_map(
                static fn(PluginInterface $plugin): string => $plugin->getName(),
                $registry->getPlugins(PluginInterface::class),
            );
            foreach ($attribute->plugins as $plugin) {
                if (!\in_array($plugin->getName(), $registeredNames, true)) {
                    $registry->add($plugin);
                    $registeredNames[] = $plugin->getName();
                }
            }
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
        $container = ContainerFacade::$container ?? null;
        if ($container === null || !$container->has(TranscriptWriter::class)) {
            return LoggerFactory::createServerLogger($feature->taskQueue);
        }
        try {
            $transcript = $container->get(TranscriptWriter::class);
            $stderr = $container->has(LoggerInterface::class)
                ? $container->get(LoggerInterface::class)
                : new NullLogger();
            return LoggerFactory::createServerLoggerWithTranscript($feature->taskQueue, $transcript, $stderr);
        } catch (\Throwable) {
            return LoggerFactory::createServerLogger($feature->taskQueue);
        }
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
