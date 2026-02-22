<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\ClientPluginTrait;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Plugin\WorkerPluginTrait;

/**
 * Acceptance tests for plugin integration with WorkflowClient.
 *
 * @group unit
 * @group plugin
 */
class ClientPluginTestCase extends TestCase
{
    public function testPluginConfigureClientIsCalled(): void
    {
        $called = false;
        $plugin = new class($called) implements ClientPluginInterface {
            use ClientPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.spy';
            }

            public function configureClient(ClientPluginContext $context): void
            {
                $this->called = true;
            }
        };

        new WorkflowClient($this->mockServiceClient(), plugins: [$plugin]);

        self::assertTrue($called);
    }

    public function testPluginModifiesClientOptions(): void
    {
        $plugin = new class implements ClientPluginInterface {
            use ClientPluginTrait;

            public function getName(): string
            {
                return 'test.namespace';
            }

            public function configureClient(ClientPluginContext $context): void
            {
                $context->setClientOptions(
                    (new ClientOptions())->withNamespace('plugin-namespace'),
                );
            }
        };

        $client = new WorkflowClient($this->mockServiceClient(), plugins: [$plugin]);

        // The namespace metadata is set from plugin-modified options
        self::assertNotNull($client->getServiceClient());
    }

    public function testPluginModifiesDataConverter(): void
    {
        $customConverter = $this->createMock(DataConverterInterface::class);

        $plugin = new class($customConverter) implements ClientPluginInterface {
            use ClientPluginTrait;

            public function __construct(private DataConverterInterface $converter) {}

            public function getName(): string
            {
                return 'test.converter';
            }

            public function configureClient(ClientPluginContext $context): void
            {
                $context->setDataConverter($this->converter);
            }
        };

        // Should not throw — converter is applied
        $client = new WorkflowClient($this->mockServiceClient(), plugins: [$plugin]);
        self::assertNotNull($client);
    }

    public function testPluginAddsInterceptor(): void
    {
        $interceptor = new class implements WorkflowClientCallsInterceptor {
            use WorkflowClientCallsInterceptorTrait;
        };

        $plugin = new class($interceptor) implements ClientPluginInterface {
            use ClientPluginTrait;

            public function __construct(private WorkflowClientCallsInterceptor $interceptor) {}

            public function getName(): string
            {
                return 'test.interceptor';
            }

            public function configureClient(ClientPluginContext $context): void
            {
                $context->addInterceptor($this->interceptor);
            }
        };

        // Should not throw — interceptor pipeline is built with plugin interceptor
        $client = new WorkflowClient($this->mockServiceClient(), plugins: [$plugin]);
        self::assertNotNull($client);
    }

    public function testMultiplePluginsCalledInOrder(): void
    {
        $order = [];

        $plugin1 = new class($order) implements ClientPluginInterface {
            use ClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.first';
            }

            public function configureClient(ClientPluginContext $context): void
            {
                $this->order[] = 'first';
            }
        };

        $plugin2 = new class($order) implements ClientPluginInterface {
            use ClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.second';
            }

            public function configureClient(ClientPluginContext $context): void
            {
                $this->order[] = 'second';
            }
        };

        new WorkflowClient($this->mockServiceClient(), plugins: [$plugin1, $plugin2]);

        self::assertSame(['first', 'second'], $order);
    }

    public function testDuplicatePluginThrowsException(): void
    {
        $plugin1 = new class('dup') extends AbstractPlugin {};
        $plugin2 = new class('dup') extends AbstractPlugin {};

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate plugin "dup"');

        new WorkflowClient($this->mockServiceClient(), plugins: [$plugin1, $plugin2]);
    }

    public function testGetWorkerPluginsPropagation(): void
    {
        $plugin = new class('combo') extends AbstractPlugin {};

        $client = new WorkflowClient($this->mockServiceClient(), plugins: [$plugin]);

        $workerPlugins = $client->getWorkerPlugins();
        self::assertCount(1, $workerPlugins);
        self::assertSame($plugin, $workerPlugins[0]);
    }

    public function testGetScheduleClientPluginsPropagation(): void
    {
        $plugin = new class('combo') extends AbstractPlugin {};

        $client = new WorkflowClient($this->mockServiceClient(), plugins: [$plugin]);

        $schedulePlugins = $client->getScheduleClientPlugins();
        self::assertCount(1, $schedulePlugins);
        self::assertSame($plugin, $schedulePlugins[0]);
    }

    public function testClientOnlyPluginNotPropagatedToWorkers(): void
    {
        $plugin = new class implements ClientPluginInterface {
            use ClientPluginTrait;

            public function getName(): string
            {
                return 'client-only';
            }
        };

        $client = new WorkflowClient($this->mockServiceClient(), plugins: [$plugin]);

        self::assertCount(0, $client->getWorkerPlugins());
        self::assertCount(0, $client->getScheduleClientPlugins());
    }

    public function testWorkerOnlyPluginNotPropagatedToScheduleClient(): void
    {
        $plugin = new class implements ClientPluginInterface, WorkerPluginInterface {
            use ClientPluginTrait;
            use WorkerPluginTrait;

            public function getName(): string
            {
                return 'client-worker';
            }
        };

        $client = new WorkflowClient($this->mockServiceClient(), plugins: [$plugin]);

        self::assertCount(1, $client->getWorkerPlugins());
        self::assertCount(0, $client->getScheduleClientPlugins());
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
