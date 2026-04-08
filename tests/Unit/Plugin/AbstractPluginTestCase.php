<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\ClientOptions;
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ScheduleClientPluginContext;
use Temporal\Plugin\WorkerFactoryPluginContext;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

/**
 * @group unit
 * @group plugin
 */
class AbstractPluginTestCase extends TestCase
{
    public function testGetName(): void
    {
        $plugin = new class('test.my-plugin') extends AbstractPlugin {};

        self::assertSame('test.my-plugin', $plugin->getName());
    }

    public function testConfigureClientPassthrough(): void
    {
        $plugin = new class('noop') extends AbstractPlugin {};
        $context = new ClientPluginContext(new ClientOptions());

        $clone = clone $context;
        $plugin->configureClient($context, static fn() => null);

        self::assertSame($clone->getClientOptions(), $context->getClientOptions());
        self::assertSame($clone->getDataConverter(), $context->getDataConverter());
    }

    public function testConfigureScheduleClientPassthrough(): void
    {
        $plugin = new class('noop') extends AbstractPlugin {};
        $context = new ScheduleClientPluginContext(new ClientOptions());

        $clone = clone $context;
        $plugin->configureScheduleClient($context, static fn() => null);

        self::assertSame($clone->getClientOptions(), $context->getClientOptions());
        self::assertSame($clone->getDataConverter(), $context->getDataConverter());
    }

    public function testConfigureWorkerFactoryPassthrough(): void
    {
        $plugin = new class('noop') extends AbstractPlugin {};
        $context = new WorkerFactoryPluginContext();

        $clone = clone $context;
        $plugin->configureWorkerFactory($context, static fn() => null);

        self::assertSame($clone->getDataConverter(), $context->getDataConverter());
    }

    public function testConfigureWorkerPassthrough(): void
    {
        $plugin = new class('noop') extends AbstractPlugin {};
        $context = new WorkerPluginContext('test-queue', WorkerOptions::new());

        $clone = clone $context;
        $plugin->configureWorker($context, static fn() => null);

        self::assertSame($clone->getWorkerOptions(), $context->getWorkerOptions());
        self::assertSame($clone->getExceptionInterceptor(), $context->getExceptionInterceptor());
    }

    public function testInitializeWorkerNoop(): void
    {
        $plugin = new class('noop') extends AbstractPlugin {};
        $worker = $this->createMock(WorkerInterface::class);

        // Should not throw
        $plugin->initializeWorker($worker, static fn() => null);
        self::assertTrue(true);
    }
}
