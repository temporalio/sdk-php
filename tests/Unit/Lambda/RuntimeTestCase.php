<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Temporal\Lambda\Clock;
use Temporal\Lambda\Config;
use Temporal\Lambda\Http\Client;
use Temporal\Lambda\Process\Handle;
use Temporal\Lambda\Process\SignalTrap;
use Temporal\Lambda\Process\Tree;
use Temporal\Lambda\RoadRunner\ConfigFile;
use Temporal\Lambda\RoadRunner\Process;
use Temporal\Lambda\Runtime;
use Temporal\Lambda\RuntimeApi;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(Runtime::class)]
#[UsesClass(Clock::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigFile::class)]
#[UsesClass(Process::class)]
#[UsesClass(Client::class)]
#[UsesClass(Handle::class)]
#[UsesClass(SignalTrap::class)]
#[UsesClass(Tree::class)]
#[UsesClass(RuntimeApi::class)]
final class RuntimeTestCase extends AbstractUnit
{
    private const GRACEFUL_MS = 1_000;
    private const BUFFER_MS = 2_700;
    private const POLL_WINDOW_MS = 1_200;

    private RuntimeApiStub $api;
    private string $workDir;
    private RecordingLogger $logger;

    public function testInvocationRunsRoadRunnerAndAcknowledges(): void
    {
        $binary = $this->fakeRoadRunner('\\sleep(30);');

        $this->runUntilTheStubStops($binary);

        self::assertSame(
            ['/2018-06-01/runtime/invocation/request-0/response'],
            $this->api->acknowledgements(),
        );
    }

    public function testRoadRunnerIsStoppedBeforeTheDeadline(): void
    {
        $binary = $this->fakeRoadRunner($this->recordStart('runs') . ' \\sleep(30);');

        $elapsed = $this->runUntilTheStubStops($binary);

        self::assertSame('x', \file_get_contents($this->workDir . '/runs'));
        self::assertLessThan(self::POLL_WINDOW_MS + self::BUFFER_MS, $elapsed);
        self::assertSame([], $this->survivingProcesses($binary));
    }

    public function testRoadRunnerCrashIsReportedAsAnInvocationError(): void
    {
        $binary = $this->fakeRoadRunner('exit(3);');

        $this->runUntilTheStubStops($binary);

        self::assertSame(
            ['/2018-06-01/runtime/invocation/request-0/error'],
            $this->api->acknowledgements(),
        );
        self::assertStringContainsString(
            'RoadRunner exited before the shutdown window with code 3',
            $this->api->lastBody(),
        );
        self::assertContains(
            'roadrunner had already exited with code 3 when the shutdown started',
            $this->logger->messages,
        );
    }

    public function testInvocationWithoutEnoughTimeIsReportedWithoutStartingRoadRunner(): void
    {
        $binary = $this->fakeRoadRunner($this->recordStart('started') . ' \\sleep(30);');
        $this->api->setPollWindowMs(0);

        $this->runUntilTheStubStops($binary);

        self::assertSame(
            ['/2018-06-01/runtime/invocation/request-0/error'],
            $this->api->acknowledgements(),
        );
        self::assertStringContainsString('Insufficient invocation time', $this->api->lastBody());
        self::assertFileDoesNotExist($this->workDir . '/started');
    }

    public function testARoadRunnerThatIgnoresSigtermIsKilled(): void
    {
        $binary = $this->fakeRoadRunner(
            '\\pcntl_async_signals(true); \\pcntl_signal(\\SIGTERM, static fn() => null); while (true) { \\sleep(1); }',
        );

        $elapsed = $this->runUntilTheStubStops($binary);

        self::assertSame([], $this->survivingProcesses($binary));
        self::assertLessThan(self::POLL_WINDOW_MS + self::BUFFER_MS, $elapsed);
        self::assertStringContainsString(
            'SIGKILLed it',
            \implode("\n", $this->logger->messages),
        );
    }

    public function testEveryInvocationGetsItsOwnRoadRunner(): void
    {
        $binary = $this->fakeRoadRunner($this->recordStart('runs') . ' \\sleep(30);');
        $this->api->setInvocations(2);

        $this->runUntilTheStubStops($binary);

        self::assertSame(
            [
                '/2018-06-01/runtime/invocation/request-0/response',
                '/2018-06-01/runtime/invocation/request-1/response',
            ],
            $this->api->acknowledgements(),
        );
        self::assertSame('xx', \file_get_contents($this->workDir . '/runs'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = \sys_get_temp_dir() . '/temporal-lambda-' . \bin2hex(\random_bytes(6));
        \mkdir($this->workDir);
        \file_put_contents($this->workDir . '/.rr.yaml', "rpc:\n  listen: tcp://127.0.0.1:6001\n");

        $this->logger = new RecordingLogger();
        $this->api = new RuntimeApiStub($this->workDir, self::POLL_WINDOW_MS + self::BUFFER_MS);
        $this->api->start();
    }

    protected function tearDown(): void
    {
        $this->api->stop();

        foreach ((array) \glob($this->workDir . '/{,.}[!.,..]*', \GLOB_BRACE) as $file) {
            @\unlink((string) $file);
        }

        \rmdir($this->workDir);
        @\unlink(ConfigFile::PATH);

        parent::tearDown();
    }

    private function runUntilTheStubStops(string $binary): int
    {
        $config = new Config(
            runtimeApi: $this->api->host(),
            taskRoot: $this->workDir,
            roadRunnerBinary: $binary,
            roadRunnerConfigTemplate: $this->workDir . '/.rr.yaml',
            shutdownBufferMs: self::BUFFER_MS,
            gracefulTimeoutMs: self::GRACEFUL_MS,
        );

        $runtime = new Runtime($config, new RuntimeApi($config->runtimeApi), $this->logger);

        $startedAt = Clock::nowMs();
        try {
            $runtime->run();
            self::fail('The runtime loop returned instead of failing on the exhausted stub');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('fetch the next invocation', $e->getMessage());
        }

        return Clock::nowMs() - $startedAt;
    }

    /**
     * @return list<int>
     */
    private function survivingProcesses(string $binary): array
    {
        $output = [];
        \exec('ps -eo pid=,command=', $output);

        $survivors = [];
        foreach ($output as $line) {
            if (\str_contains($line, $binary)) {
                $survivors[] = (int) \trim($line);
            }
        }

        return $survivors;
    }

    private function recordStart(string $file): string
    {
        return \sprintf(
            '\\file_put_contents(%s, "x", \\FILE_APPEND);',
            \var_export($this->workDir . '/' . $file, true),
        );
    }

    private function fakeRoadRunner(string $body): string
    {
        $path = $this->workDir . '/rr';
        \file_put_contents($path, "#!/usr/bin/env php\n<?php\n{$body}\n");
        \chmod($path, 0o755);

        return $path;
    }
}
