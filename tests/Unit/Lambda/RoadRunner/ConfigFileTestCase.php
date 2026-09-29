<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda\RoadRunner;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Lambda\Config;
use Temporal\Lambda\RoadRunner\ConfigFile;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(ConfigFile::class)]
final class ConfigFileTestCase extends AbstractUnit
{
    private string $template;

    public function testGracePeriodIsAppendedToTheTemplate(): void
    {
        \file_put_contents($this->template, "rpc:\n  listen: tcp://127.0.0.1:6001\n");

        $path = ConfigFile::render($this->config(gracefulTimeoutMs: 4_500));

        self::assertSame(
            "rpc:\n  listen: tcp://127.0.0.1:6001\n\nendure:\n  grace_period: 4500ms\n",
            \file_get_contents($path),
        );
    }

    public function testTrailingBlankLinesDoNotProduceAStrayGap(): void
    {
        \file_put_contents($this->template, "rpc:\n  listen: tcp://127.0.0.1:6001\n\n\n\n");

        $path = ConfigFile::render($this->config(gracefulTimeoutMs: 5_000));

        self::assertSame(
            "rpc:\n  listen: tcp://127.0.0.1:6001\n\nendure:\n  grace_period: 5000ms\n",
            \file_get_contents($path),
        );
    }

    public function testRenderOverwritesAPreviousRender(): void
    {
        \file_put_contents($this->template, "rpc:\n  listen: tcp://127.0.0.1:6001\n");

        ConfigFile::render($this->config(gracefulTimeoutMs: 1_000));
        $path = ConfigFile::render($this->config(gracefulTimeoutMs: 2_000));

        self::assertStringNotContainsString('1000ms', (string) \file_get_contents($path));
        self::assertStringContainsString('2000ms', (string) \file_get_contents($path));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->template = \tempnam(\sys_get_temp_dir(), 'rr-template-');
    }

    protected function tearDown(): void
    {
        @\unlink($this->template);
        @\unlink(ConfigFile::PATH);

        parent::tearDown();
    }

    private function config(int $gracefulTimeoutMs): Config
    {
        return new Config(
            runtimeApi: '127.0.0.1:9001',
            taskRoot: \sys_get_temp_dir(),
            roadRunnerBinary: 'rr',
            roadRunnerConfigTemplate: $this->template,
            shutdownBufferMs: 30_000,
            gracefulTimeoutMs: $gracefulTimeoutMs,
        );
    }
}
