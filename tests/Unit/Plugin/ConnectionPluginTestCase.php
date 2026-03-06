<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\ScheduleClient;
use Temporal\Client\WorkflowClient;
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\ClientPluginTrait;
use Temporal\Plugin\ConnectionPluginContext;
use Temporal\Plugin\ConnectionPluginInterface;
use Temporal\Plugin\ConnectionPluginTrait;
use Temporal\Plugin\PluginRegistry;
use Temporal\Plugin\ScheduleClientPluginInterface;
use Temporal\Plugin\ScheduleClientPluginTrait;

/**
 * Tests for ConnectionPluginInterface integration with WorkflowClient and ScheduleClient.
 *
 * @group unit
 * @group plugin
 */
class ConnectionPluginTestCase extends TestCase
{
    public function testConfigureServiceClientCalledFromWorkflowClient(): void
    {
        $called = false;
        $plugin = new class($called) implements ConnectionPluginInterface, ClientPluginInterface {
            use ConnectionPluginTrait;
            use ClientPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.connection';
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $this->called = true;
            }
        };

        new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        self::assertTrue($called);
    }

    public function testConfigureServiceClientCalledFromScheduleClient(): void
    {
        $called = false;
        $plugin = new class($called) implements ConnectionPluginInterface, ScheduleClientPluginInterface {
            use ConnectionPluginTrait;
            use ScheduleClientPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.connection';
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $this->called = true;
            }
        };

        new ScheduleClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        self::assertTrue($called);
    }

    public function testPluginModifiesServiceClientViaWithAuthKey(): void
    {
        $authedClient = $this->mockServiceClient();

        $originalClient = $this->createMock(ServiceClientInterface::class);
        $originalClient->method('withAuthKey')->willReturn($authedClient);
        // Allow context calls for the client constructor
        $context = $this->createMock(ContextInterface::class);
        $context->method('getMetadata')->willReturn([]);
        $context->method('withMetadata')->willReturn($context);
        $originalClient->method('getContext')->willReturn($context);
        $originalClient->method('withContext')->willReturn($originalClient);

        $plugin = new class implements ConnectionPluginInterface, ClientPluginInterface {
            use ConnectionPluginTrait;
            use ClientPluginTrait;

            public function getName(): string
            {
                return 'test.auth';
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $context->setServiceClient(
                    $context->getServiceClient()->withAuthKey('my-api-key'),
                );
            }
        };

        $client = new WorkflowClient($originalClient, pluginRegistry: new PluginRegistry([$plugin]));

        // The service client should be the authed version
        self::assertSame($authedClient, $client->getServiceClient());
    }

    public function testPluginAddsMetadataViaContext(): void
    {
        $metadataSet = null;

        $context = $this->createMock(ContextInterface::class);
        $context->method('getMetadata')->willReturn([]);
        $context->method('withMetadata')->willReturnCallback(
            static function (array $metadata) use ($context, &$metadataSet) {
                $metadataSet = $metadata;
                return $context;
            },
        );

        $serviceClient = $this->createMock(ServiceClientInterface::class);
        $serviceClient->method('getContext')->willReturn($context);
        $serviceClient->method('withContext')->willReturn($serviceClient);

        $plugin = new class implements ConnectionPluginInterface, ClientPluginInterface {
            use ConnectionPluginTrait;
            use ClientPluginTrait;

            public function getName(): string
            {
                return 'test.metadata';
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $client = $context->getServiceClient();
                $ctx = $client->getContext();
                $context->setServiceClient(
                    $client->withContext(
                        $ctx->withMetadata(['x-custom-header' => ['value']] + $ctx->getMetadata()),
                    ),
                );
            }
        };

        new WorkflowClient($serviceClient, pluginRegistry: new PluginRegistry([$plugin]));

        // Metadata should have been set (by plugin and then by WorkflowClient for namespace)
        self::assertNotNull($metadataSet);
    }

    public function testMultipleConnectionPluginsCalledInOrder(): void
    {
        $order = [];

        $plugin1 = new class($order) implements ConnectionPluginInterface, ClientPluginInterface {
            use ConnectionPluginTrait;
            use ClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.first';
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $this->order[] = 'first';
            }
        };

        $plugin2 = new class($order) implements ConnectionPluginInterface, ClientPluginInterface {
            use ConnectionPluginTrait;
            use ClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.second';
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $this->order[] = 'second';
            }
        };

        new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin1, $plugin2]));

        self::assertSame(['first', 'second'], $order);
    }

    public function testConnectionPluginRunsBeforeClientPlugin(): void
    {
        $order = [];

        $plugin = new class($order) implements ConnectionPluginInterface, ClientPluginInterface {
            use ConnectionPluginTrait;
            use ClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.order';
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $this->order[] = 'connection';
            }

            public function configureClient(ClientPluginContext $context): void
            {
                $this->order[] = 'client';
            }
        };

        new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        self::assertSame(['connection', 'client'], $order);
    }

    public function testDefaultTraitIsNoOp(): void
    {
        $plugin = new class('test.noop') extends AbstractPlugin {};

        // Should not throw — all trait methods are no-ops
        $client = new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));
        self::assertNotNull($client);
    }

    public function testAbstractPluginWorksWithConnectionPlugin(): void
    {
        $called = false;

        $plugin = new class($called) extends AbstractPlugin {
            private bool $ref;

            public function __construct(bool &$called)
            {
                parent::__construct('test.abstract');
                $this->ref = &$called;
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $this->ref = true;
            }
        };

        new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        self::assertTrue($called);
    }

    public function testConnectionOnlyPluginNotRegisteredAsClientPlugin(): void
    {
        // A plugin implementing only ConnectionPluginInterface
        // should still work when passed to WorkflowClient
        $called = false;
        $plugin = new class($called) implements ConnectionPluginInterface {
            use ConnectionPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.conn-only';
            }

            public function configureServiceClient(ConnectionPluginContext $context): void
            {
                $this->called = true;
            }
        };

        new WorkflowClient($this->mockServiceClient(), pluginRegistry: new PluginRegistry([$plugin]));

        self::assertTrue($called);
    }

    private function mockServiceClient(): ServiceClientInterface
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('getMetadata')->willReturn([]);
        $context->method('withMetadata')->willReturn($context);

        $client = $this->createMock(ServiceClientInterface::class);
        $client->method('getContext')->willReturn($context);
        $client->method('withContext')->willReturn($client);
        $client->method('withAuthKey')->willReturn($client);

        return $client;
    }
}
