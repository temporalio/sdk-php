<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\PluginRegistry;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Tests\Acceptance\App\Interceptor\TranscriptActivityInterceptor;
use Temporal\Tests\Acceptance\App\Interceptor\TranscriptWorkflowInterceptor;
use Temporal\Tests\Acceptance\App\Plugin\TranscriptPlugin;
use Temporal\Worker\WorkerOptions;

#[CoversClass(TranscriptPlugin::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(PluginRegistry::class)]
#[UsesClass(WorkerPluginContext::class)]
final class TranscriptPluginTestCase extends TestCase
{
    public function testGetNameReturnsCanonicalIdentifier(): void
    {
        $plugin = new TranscriptPlugin();

        self::assertSame('temporal-php.transcript', $plugin->getName());
        self::assertSame(TranscriptPlugin::NAME, $plugin->getName());
    }

    public function testConfigureWorkerAddsActivityAndWorkflowInterceptors(): void
    {
        $plugin = new TranscriptPlugin();
        $context = new WorkerPluginContext('test-queue', WorkerOptions::new());
        $nextCalled = false;

        $plugin->configureWorker($context, static function (WorkerPluginContext $received) use (&$nextCalled, $context): void {
            $nextCalled = true;
            self::assertSame($context, $received);
        });

        self::assertTrue($nextCalled, 'next callback must be invoked');
        $interceptors = $context->getInterceptors();
        self::assertCount(2, $interceptors);
        self::assertInstanceOf(TranscriptActivityInterceptor::class, $interceptors[0]);
        self::assertInstanceOf(TranscriptWorkflowInterceptor::class, $interceptors[1]);
    }

    public function testConfigureWorkerAppendsInterceptorsWithoutClobberingExistingOnes(): void
    {
        $plugin = new TranscriptPlugin();
        $context = new WorkerPluginContext('test-queue', WorkerOptions::new());
        $existing = new TranscriptActivityInterceptor();
        $context->addInterceptor($existing);

        $plugin->configureWorker($context, static fn() => null);

        $interceptors = $context->getInterceptors();
        self::assertCount(3, $interceptors);
        self::assertSame($existing, $interceptors[0]);
        self::assertInstanceOf(TranscriptActivityInterceptor::class, $interceptors[1]);
        self::assertInstanceOf(TranscriptWorkflowInterceptor::class, $interceptors[2]);
    }

    public function testRegistryExposesPluginUnderWorkerPluginInterface(): void
    {
        $registry = new PluginRegistry();
        $plugin = new TranscriptPlugin();
        $registry->add($plugin);

        $workerPlugins = $registry->getPlugins(WorkerPluginInterface::class);
        self::assertCount(1, $workerPlugins);
        self::assertSame($plugin, $workerPlugins[0]);
    }

    public function testRegistryRejectsDuplicateRegistration(): void
    {
        $registry = new PluginRegistry();
        $registry->add(new TranscriptPlugin());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate plugin "temporal-php.transcript": a plugin with this name is already registered.');

        $registry->add(new TranscriptPlugin());
    }
}
