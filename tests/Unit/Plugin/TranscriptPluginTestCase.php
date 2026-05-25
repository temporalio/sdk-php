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
use Temporal\Testing\Transcript\TranscriptActivityInterceptor;
use Temporal\Testing\Transcript\TranscriptWorkflowInterceptor;
use Temporal\Testing\Transcript\TranscriptWriter;
use Temporal\Testing\Transcript\TranscriptPlugin;
use Temporal\Tests\Unit\Logger\TranscriptTestSupport;
use Temporal\Worker\WorkerOptions;

#[CoversClass(TranscriptPlugin::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(PluginRegistry::class)]
#[UsesClass(WorkerPluginContext::class)]
#[UsesClass(WorkerOptions::class)]
#[UsesClass(TranscriptActivityInterceptor::class)]
#[UsesClass(TranscriptWorkflowInterceptor::class)]
final class TranscriptPluginTestCase extends TestCase
{
    use TranscriptTestSupport;

    public function testGetNameReturnsCanonicalIdentifier(): void
    {
        $plugin = new TranscriptPlugin($this->newWriter());

        self::assertSame('temporal-php.transcript', $plugin->getName());
    }

    public function testConfigureWorkerAddsActivityAndWorkflowInterceptors(): void
    {
        $writer = $this->newWriter();
        $plugin = new TranscriptPlugin($writer);
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
        $writer = $this->newWriter();
        $plugin = new TranscriptPlugin($writer);
        $context = new WorkerPluginContext('test-queue', WorkerOptions::new());
        $existing = new TranscriptActivityInterceptor($writer);
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
        $plugin = new TranscriptPlugin($this->newWriter());
        $registry->add($plugin);

        $workerPlugins = $registry->getPlugins(WorkerPluginInterface::class);
        self::assertCount(1, $workerPlugins);
        self::assertSame($plugin, $workerPlugins[0]);
    }

    private function newWriter(): TranscriptWriter
    {
        return new TranscriptWriter($this->directory . '/' . \uniqid('plugin-', true) . '.log');
    }
}
