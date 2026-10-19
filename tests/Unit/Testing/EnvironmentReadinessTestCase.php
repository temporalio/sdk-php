<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Temporal\Testing\Command;
use Temporal\Testing\Environment;
use Temporal\Testing\SystemInfo;

final class EnvironmentReadinessTestCase extends TestCase
{
    private string $dir;

    public function testASlowTemporalHealthCheckIsRetriedInsteadOfAbortingTheStart(): void
    {
        $environment = $this->environment($this->cliStub());

        $environment->startTemporalServer(commandTimeout: 20);

        self::assertTrue($environment->isTemporalRunning());
        $environment->stop();
    }

    public function testASlowRoadRunnerCheckIsRetriedInsteadOfAbortingTheStart(): void
    {
        $environment = $this->environment($this->cliStub(), $this->cliStub('rr'));

        $environment->startTemporalServer(commandTimeout: 20);
        $environment->startRoadRunner([$this->dir . '/rr', 'serve'], commandTimeout: 20);

        self::assertTrue($environment->isRoadRunnerRunning());
        $environment->stop();
    }

    protected function setUp(): void
    {
        $this->dir = \sys_get_temp_dir() . '/temporal-readiness-' . \bin2hex(\random_bytes(4));
        \mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->dir . '/*') ?: [] as $file) {
            \unlink($file);
        }

        \is_dir($this->dir) and \rmdir($this->dir);
    }

    /**
     * A stub whose first readiness answer takes longer than the check allows.
     */
    private function cliStub(string $name = 'temporal'): string
    {
        $path = $this->dir . '/' . $name;
        $marker = $this->dir . '/' . $name . '-checked';

        \file_put_contents($path, <<<SH
            #!/usr/bin/env bash
            case "\$*" in
                *"start-dev"*) sleep 60 ;;
                *"serve"*) echo "RoadRunner server started"; sleep 60 ;;
                *)
                    if [ ! -f "$marker" ]; then
                        touch "$marker"
                        sleep 3
                        exit 0
                    fi
                    echo "SERVING"
                    echo "Workers of [temporal]:"
                    ;;
            esac
            SH);
        \chmod($path, 0o755);

        return $path;
    }

    private function environment(string $temporalCli, ?string $rr = null): Environment
    {
        $reflection = new \ReflectionClass(SystemInfo::class);
        /** @var SystemInfo $systemInfo */
        $systemInfo = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('arch')->setValue($systemInfo, 'amd64');
        $reflection->getProperty('platform')->setValue($systemInfo, 'linux');
        $reflection->getProperty('os')->setValue($systemInfo, 'linux');
        $reflection->getProperty('temporalServerExecutable')->setValue($systemInfo, $temporalCli);
        $reflection->getProperty('rrExecutable')->setValue($systemInfo, $rr ?? $temporalCli);
        $reflection->getProperty('temporalCliExecutable')->setValue($systemInfo, $temporalCli);

        return Environment::create(new Command('127.0.0.1:' . $this->freePort()), $systemInfo);
    }

    private function freePort(): int
    {
        $socket = \stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) \explode(':', \stream_socket_get_name($socket, false))[1];
        \fclose($socket);

        return $port;
    }
}
