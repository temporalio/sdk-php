<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\ScheduleClient;
use Temporal\Client\WorkflowClient;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\ClientPluginTrait;
use Temporal\Plugin\PluginRegistry;
use Temporal\Plugin\ScheduleClientPluginContext;
use Temporal\Plugin\ScheduleClientPluginInterface;
use Temporal\Plugin\ScheduleClientPluginTrait;
use Temporal\Plugin\WorkerFactoryPluginContext;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Plugin\WorkerPluginTrait;
use Temporal\WorkerFactory;
use Temporal\Worker\WorkerInterface;

class PluginChainTestCase extends TestCase
{
    public function testClientPluginInterception(): void
    {
        $order = [];
        $plugin = new class($order) implements ClientPluginInterface {
            use ClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test';
            }

            public function configureClient(ClientPluginContext $context, callable $next): void
            {
                $this->order[] = 'before';
                $next($context);
                $this->order[] = 'after';
            }
        };

        new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        self::assertSame(['before', 'after'], $order);
    }

    public function testScheduleClientPluginInterception(): void
    {
        $order = [];
        $plugin = new class($order) implements ScheduleClientPluginInterface {
            use ScheduleClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test';
            }

            public function configureScheduleClient(ScheduleClientPluginContext $context, callable $next): void
            {
                $this->order[] = 'before';
                $next($context);
                $this->order[] = 'after';
            }
        };

        new ScheduleClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        self::assertSame(['before', 'after'], $order);
    }

    public function testWorkerFactoryPluginInterception(): void
    {
        $order = [];
        $plugin = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test';
            }

            public function configureWorkerFactory(WorkerFactoryPluginContext $context, callable $next): void
            {
                $this->order[] = 'before';
                $next($context);
                $this->order[] = 'after';
            }
        };

        WorkerFactory::create(pluginRegistry: new PluginRegistry([$plugin]));

        self::assertSame(['before', 'after'], $order);
    }

    public function testWorkerPluginInterception(): void
    {
        $order = [];
        $plugin = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test';
            }

            public function configureWorker(WorkerPluginContext $context, callable $next): void
            {
                $this->order[] = 'before_config';
                $next($context);
                $this->order[] = 'after_config';
            }

            public function initializeWorker(WorkerInterface $worker, callable $next): void
            {
                $this->order[] = 'before_init';
                $next($worker);
                $this->order[] = 'after_init';
            }
        };

        $factory = WorkerFactory::create(pluginRegistry: new PluginRegistry([$plugin]));
        $factory->newWorker('test-queue');

        self::assertSame(['before_config', 'after_config', 'before_init', 'after_init'], $order);
    }

    private function mockServiceClient(): ServiceClientInterface
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('getMetadata')->willReturn([]);
        $context->method('withMetadata')->willReturn($context);

        $client = $this->createMock(ServiceClientInterface::class);
        $client->method('getContext')->willReturn($context);
        $client->method('withContext')->willReturn($client);

        return $client;
    }
}
