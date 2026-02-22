<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\ScheduleClient;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Plugin\ScheduleClientPluginContext;
use Temporal\Plugin\ScheduleClientPluginInterface;
use Temporal\Plugin\ScheduleClientPluginTrait;

/**
 * Acceptance tests for plugin integration with ScheduleClient.
 *
 * @group unit
 * @group plugin
 */
class ScheduleClientPluginTestCase extends TestCase
{
    public function testPluginConfigureScheduleClientIsCalled(): void
    {
        $called = false;
        $plugin = new class($called) implements ScheduleClientPluginInterface {
            use ScheduleClientPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.spy';
            }

            public function configureScheduleClient(ScheduleClientPluginContext $context): void
            {
                $this->called = true;
            }
        };

        new ScheduleClient($this->mockServiceClient(), plugins: [$plugin]);

        self::assertTrue($called);
    }

    public function testPluginModifiesClientOptions(): void
    {
        $plugin = new class implements ScheduleClientPluginInterface {
            use ScheduleClientPluginTrait;

            public function getName(): string
            {
                return 'test.namespace';
            }

            public function configureScheduleClient(ScheduleClientPluginContext $context): void
            {
                $context->setClientOptions(
                    (new ClientOptions())->withNamespace('schedule-namespace'),
                );
            }
        };

        $client = new ScheduleClient($this->mockServiceClient(), plugins: [$plugin]);
        self::assertNotNull($client);
    }

    public function testPluginModifiesDataConverter(): void
    {
        $customConverter = $this->createMock(DataConverterInterface::class);

        $plugin = new class($customConverter) implements ScheduleClientPluginInterface {
            use ScheduleClientPluginTrait;

            public function __construct(private DataConverterInterface $converter) {}

            public function getName(): string
            {
                return 'test.converter';
            }

            public function configureScheduleClient(ScheduleClientPluginContext $context): void
            {
                $context->setDataConverter($this->converter);
            }
        };

        $client = new ScheduleClient($this->mockServiceClient(), plugins: [$plugin]);
        self::assertNotNull($client);
    }

    public function testMultiplePluginsCalledInOrder(): void
    {
        $order = [];

        $plugin1 = new class($order) implements ScheduleClientPluginInterface {
            use ScheduleClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.first';
            }

            public function configureScheduleClient(ScheduleClientPluginContext $context): void
            {
                $this->order[] = 'first';
            }
        };

        $plugin2 = new class($order) implements ScheduleClientPluginInterface {
            use ScheduleClientPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.second';
            }

            public function configureScheduleClient(ScheduleClientPluginContext $context): void
            {
                $this->order[] = 'second';
            }
        };

        new ScheduleClient($this->mockServiceClient(), plugins: [$plugin1, $plugin2]);

        self::assertSame(['first', 'second'], $order);
    }

    public function testNoPluginsDoesNotBreak(): void
    {
        $client = new ScheduleClient($this->mockServiceClient());
        self::assertNotNull($client);
    }

    public function testPluginReceivesCorrectInitialContext(): void
    {
        $initialOptions = (new ClientOptions())->withNamespace('initial-ns');
        $initialConverter = $this->createMock(DataConverterInterface::class);

        $receivedOptions = null;
        $receivedConverter = null;

        $plugin = new class($receivedOptions, $receivedConverter) implements ScheduleClientPluginInterface {
            use ScheduleClientPluginTrait;

            public function __construct(
                private ?ClientOptions &$receivedOptions,
                private ?DataConverterInterface &$receivedConverter,
            ) {}

            public function getName(): string
            {
                return 'test.inspector';
            }

            public function configureScheduleClient(ScheduleClientPluginContext $context): void
            {
                $this->receivedOptions = $context->getClientOptions();
                $this->receivedConverter = $context->getDataConverter();
            }
        };

        new ScheduleClient(
            $this->mockServiceClient(),
            options: $initialOptions,
            converter: $initialConverter,
            plugins: [$plugin],
        );

        self::assertSame($initialOptions, $receivedOptions);
        self::assertSame($initialConverter, $receivedConverter);
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
