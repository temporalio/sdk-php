<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\ClientPluginTrait;
use Temporal\Plugin\PluginInterface;
use Temporal\Plugin\PluginRegistry;
use Temporal\Plugin\ScheduleClientPluginContext;
use Temporal\Plugin\ScheduleClientPluginInterface;
use Temporal\Plugin\ScheduleClientPluginTrait;
use Temporal\Plugin\WorkerFactoryPluginContext;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Plugin\WorkerPluginTrait;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\WorkerFactory;

/**
 * Tests plugin propagation across SDK components:
 * WorkflowClient → WorkerFactory (via getWorkerPlugins)
 * WorkflowClient → ScheduleClient (via getScheduleClientPlugins)
 *
 * @group unit
 * @group plugin
 */
class PluginPropagationTestCase extends TestCase
{
    public function testPluginPropagatesFromClientToWorkerFactory(): void
    {
        $order = [];

        $plugin = new class($order) extends AbstractPlugin {
            public function __construct(private array &$order)
            {
                parent::__construct('test.propagation');
            }

            public function configureClient(ClientPluginContext $context): void
            {
                $this->order[] = 'configureClient';
            }

            public function configureWorkerFactory(WorkerFactoryPluginContext $context): void
            {
                $this->order[] = 'configureWorkerFactory';
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $this->order[] = 'configureWorker';
            }

            public function initializeWorker(WorkerInterface $worker): void
            {
                $this->order[] = 'initializeWorker';
            }
        };

        $client = new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        self::assertSame(['configureClient'], $order);

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            client: $client,
        );

        self::assertSame(['configureClient', 'configureWorkerFactory'], $order);

        $factory->newWorker('test-queue');

        self::assertSame([
            'configureClient',
            'configureWorkerFactory',
            'configureWorker',
            'initializeWorker',
        ], $order);
    }

    public function testPluginFromClientMergesWithFactoryPlugins(): void
    {
        $order = [];

        $clientPlugin = new class($order) extends AbstractPlugin {
            public function __construct(private array &$order)
            {
                parent::__construct('test.from-client');
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $this->order[] = 'from-client';
            }
        };

        $factoryPlugin = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.from-factory';
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $this->order[] = 'from-factory';
            }
        };

        $client = new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$clientPlugin]));

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$factoryPlugin]),
            client: $client,
        );
        $factory->newWorker();

        self::assertSame(['from-factory', 'from-client'], $order);
    }

    public function testDuplicateAcrossClientAndFactoryThrows(): void
    {
        $clientPlugin = new class('shared-name') extends AbstractPlugin {};

        $factoryPlugin = new class implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function getName(): string
            {
                return 'shared-name';
            }
        };

        $client = new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$clientPlugin]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate plugin "shared-name"');

        new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$factoryPlugin]),
            client: $client,
        );
    }

    public function testClientOnlyPluginNotPropagatedToFactory(): void
    {
        $factoryConfigureCalled = false;

        $plugin = new class($factoryConfigureCalled) implements ClientPluginInterface {
            use ClientPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.client-only';
            }
        };

        $client = new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        // Client-only plugin should NOT appear in getWorkerPlugins
        self::assertCount(0, $client->getWorkerPlugins());

        // Factory should work fine without this plugin
        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            client: $client,
        );
        $factory->newWorker();

        self::assertCount(0, $factory->getPluginRegistry()->getPlugins(PluginInterface::class));
    }

    public function testScheduleClientPluginPropagation(): void
    {
        $called = false;

        $plugin = new class($called) implements ClientPluginInterface, ScheduleClientPluginInterface {
            use ClientPluginTrait;
            use ScheduleClientPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.schedule-combo';
            }

            public function configureScheduleClient(ScheduleClientPluginContext $context): void
            {
                $this->called = true;
            }
        };

        $client = new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        $schedulePlugins = $client->getScheduleClientPlugins();
        self::assertCount(1, $schedulePlugins);
        self::assertSame($plugin, $schedulePlugins[0]);
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

    private function mockRpc(): RPCConnectionInterface
    {
        return $this->createMock(RPCConnectionInterface::class);
    }
}
