<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

final class Environment
{
    private Downloader $downloader;
    private Output $output;
    private SystemInfo $systemInfo;
    private ?Process $temporalTestServerProcess = null;
    private ?Process $temporalServerProcess = null;
    private ?Process $roadRunnerProcess = null;

    public function __construct(Output $output, Downloader $downloader, SystemInfo $systemInfo)
    {
        $this->downloader = $downloader;
        $this->systemInfo = $systemInfo;
        $this->output = $output;
    }

    public static function create(): self
    {
        $token = \getenv('GITHUB_TOKEN');

        return new self(
            new ConsoleOutput(),
            new Downloader(new Filesystem(), HttpClient::create([
                'headers' => [
                    'authorization' => $token ? 'token ' . $token : null,
                ],
            ])),
            SystemInfo::detect(),
        );
    }

    /**
     * @param array<string, mixed> $envs
     */
    public function start(string $rrCommand = null, int $commandTimeout = 10, array $envs = []): void
    {
        $this->startTemporalTestServer($commandTimeout);
        $this->startRoadRunner($rrCommand, $commandTimeout, $envs);
    }

    public function startTemporalServer(int $commandTimeout = 10): void
    {
        $temporalPort = \parse_url(\getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233', PHP_URL_PORT);

        $this->output->write('Starting Temporal test server... ');
        $this->temporalServerProcess = new Process(
            [
                $this->systemInfo->temporalCliExecutable,
                "server", "start-dev",
                "--port", $temporalPort,
                '--dynamic-config-value', 'frontend.enableUpdateWorkflowExecution=true',
                '--dynamic-config-value', 'frontend.enableUpdateWorkflowExecutionAsyncAccepted=true',
                '--dynamic-config-value', 'frontend.enableExecuteMultiOperation=true',
                '--dynamic-config-value', 'system.enableEagerWorkflowStart=true',
                '--search-attribute', 'foo=text',
                '--search-attribute', 'bar=int',
                '--log-level', 'error',
                '--headless',
            ],
        );
        $this->temporalServerProcess->setTimeout($commandTimeout);
        $this->temporalServerProcess->start();
        $this->output->writeln('<info>done.</info>');
        \sleep(1);

        if (!$this->temporalServerProcess->isRunning()) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln('Error starting Temporal server: ' . $this->temporalServerProcess->getErrorOutput());
            exit(1);
        }
    }

    public function startTemporalTestServer(int $commandTimeout = 10): void
    {
        if (!$this->downloader->check($this->systemInfo->temporalServerExecutable)) {
            $this->output->write('Download temporal test server... ');
            $this->downloader->download($this->systemInfo);
            $this->output->writeln('<info>done.</info>');
        }

        $temporalPort = \parse_url(\getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233', PHP_URL_PORT);

        $this->output->write('Starting Temporal test server... ');
        $this->temporalTestServerProcess = new Process(
            [$this->systemInfo->temporalServerExecutable, $temporalPort, '--enable-time-skipping'],
        );
        $this->temporalTestServerProcess->setTimeout($commandTimeout);
        $this->temporalTestServerProcess->start();
        $this->output->writeln('<info>done.</info>');
        \sleep(1);

        if (!$this->temporalTestServerProcess->isRunning()) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln('Error starting Temporal Test server: ' . $this->temporalTestServerProcess->getErrorOutput());
            exit(1);
        }
    }

    /**
     * @param array<string, mixed> $envs
     */
    public function startRoadRunner(string $rrCommand = null, int $commandTimeout = 10, array $envs = []): void
    {
        $this->roadRunnerProcess = new Process(
            command: $rrCommand ? \explode(' ', $rrCommand) : [$this->systemInfo->rrExecutable, 'serve'],
            env: $envs,
        );
        $this->roadRunnerProcess->setTimeout($commandTimeout);

        $this->output->write('Starting RoadRunner... ');
        $roadRunnerStarted = false;
        $this->roadRunnerProcess->start(static function ($type, $output) use (&$roadRunnerStarted): void {
            if ($type === Process::OUT && \str_contains($output, 'RoadRunner server started')) {
                $roadRunnerStarted = true;
            }
        });

        if (!$this->roadRunnerProcess->isRunning()) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln('Error starting RoadRunner: ' . $this->roadRunnerProcess->getErrorOutput());
            exit(1);
        }

        // wait for roadrunner to start
        $ticks = $commandTimeout * 10;
        while (!$roadRunnerStarted && $ticks > 0) {
            $this->roadRunnerProcess->getStatus();
            \usleep(100000);
            --$ticks;
        }

        if (!$roadRunnerStarted) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln('Error starting RoadRunner: ' . $this->roadRunnerProcess->getErrorOutput());
            exit(1);
        }

        $this->output->writeln('<info>done.</info>');
    }

    public function stop(): void
    {
        if ($this->temporalServerProcess !== null && $this->temporalServerProcess->isRunning()) {
            $this->output->write('Stopping Temporal server... ');
            $this->temporalServerProcess->stop();
            $this->output->writeln('<info>done.</info>');
        }

        if ($this->temporalTestServerProcess !== null && $this->temporalTestServerProcess->isRunning()) {
            $this->output->write('Stopping Temporal Test server... ');
            $this->temporalTestServerProcess->stop();
            $this->output->writeln('<info>done.</info>');
        }

        if ($this->roadRunnerProcess !== null && $this->roadRunnerProcess->isRunning()) {
            $this->output->write('Stopping RoadRunner... ');
            $this->roadRunnerProcess->stop();
            $this->output->writeln('<info>done.</info>');
        }
    }
}
