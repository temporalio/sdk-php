<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\PluginRegistry;
use Temporal\Plugin\ScheduleClientPluginInterface;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Plugin\WorkerPluginTrait;

/**
 * @group unit
 * @group plugin
 */
class PluginRegistryTestCase extends TestCase
{
    public function testAddAndRetrievePlugins(): void
    {
        $plugin1 = $this->createPlugin('plugin-1');
        $plugin2 = $this->createPlugin('plugin-2');

        $registry = new PluginRegistry([$plugin1, $plugin2]);

        // AbstractPlugin implements all three interfaces (via TemporalPluginInterface),
        // so we can retrieve both via any of them
        $plugins = $registry->getPlugins(ClientPluginInterface::class);
        self::assertCount(2, $plugins);
        self::assertSame($plugin1, $plugins[0]);
        self::assertSame($plugin2, $plugins[1]);
    }

    public function testDuplicateThrowsException(): void
    {
        $plugin1 = $this->createPlugin('my-plugin');
        $plugin2 = $this->createPlugin('my-plugin');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate plugin "my-plugin"');

        new PluginRegistry([$plugin1, $plugin2]);
    }

    public function testDuplicateViaAddThrowsException(): void
    {
        $registry = new PluginRegistry();
        $registry->add($this->createPlugin('dup-plugin'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate plugin "dup-plugin"');

        $registry->add($this->createPlugin('dup-plugin'));
    }

    public function testMergeThrowsOnDuplicates(): void
    {
        $plugin1 = $this->createPlugin('plugin-a');
        $plugin2 = $this->createPlugin('plugin-b');
        $plugin3 = $this->createPlugin('plugin-a'); // duplicate

        $registry = new PluginRegistry([$plugin1]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate plugin "plugin-a"');

        $registry->merge([$plugin2, $plugin3]);
    }

    public function testGetPluginsByInterface(): void
    {
        $clientPlugin = new class implements ClientPluginInterface {
            public function getName(): string
            {
                return 'client-only';
            }

            public function configureClient(ClientPluginContext $context, callable $next): void {}
        };

        $workerPlugin = new class implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function getName(): string
            {
                return 'worker-only';
            }
        };

        $bothPlugin = $this->createPlugin('both');

        $registry = new PluginRegistry([$clientPlugin, $workerPlugin, $bothPlugin]);

        $clientPlugins = $registry->getPlugins(ClientPluginInterface::class);
        self::assertCount(2, $clientPlugins);
        self::assertSame($clientPlugin, $clientPlugins[0]);
        self::assertSame($bothPlugin, $clientPlugins[1]);

        $workerPlugins = $registry->getPlugins(WorkerPluginInterface::class);
        self::assertCount(2, $workerPlugins);
        self::assertSame($workerPlugin, $workerPlugins[0]);
        self::assertSame($bothPlugin, $workerPlugins[1]);

        $schedulePlugins = $registry->getPlugins(ScheduleClientPluginInterface::class);
        self::assertCount(1, $schedulePlugins);
        self::assertSame($bothPlugin, $schedulePlugins[0]);
    }

    public function testEmptyRegistry(): void
    {
        $registry = new PluginRegistry();

        self::assertSame([], $registry->getPlugins(ClientPluginInterface::class));
    }

    private function createPlugin(string $name): AbstractPlugin
    {
        return new class($name) extends AbstractPlugin {};
    }
}
