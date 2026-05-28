<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Feature;

use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InvokerInterface;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Logger\LoggerFactory;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Spiral\Core\Container\InjectorInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

/**
 * @implements InjectorInterface<WorkflowStubInterface>
 */
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
        // Find Worker attribute
        $attr = self::findAttribute(
            ...\array_map(static fn(array $check): string => $check[0], $feature->checks),
            ...$feature->workflows,
            ...$feature->activities,
        );
        $options = $attr?->options === null ? null : $this->invoker->invoke($attr->options);
        $interceptorProvider = $attr?->pipelineProvider === null ? null : $this->invoker->invoke($attr->pipelineProvider);
        $logger = $attr?->logger === null ? null : $this->invoker->invoke($attr->logger);

        // Add plugins from the attribute to the factory's registry (already instantiated, no invoker needed)
        if ($attr?->plugins !== null) {
            $this->workerFactory->getPluginRegistry()->merge($attr->plugins);
        }

        return $this->workerFactory->newWorker(
            $feature->taskQueue,
            $options ?? WorkerOptions::new()->withMaxConcurrentActivityExecutionSize(10),
            interceptorProvider: $interceptorProvider,
            logger: $logger ?? LoggerFactory::createServerLogger($feature->taskQueue),
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
