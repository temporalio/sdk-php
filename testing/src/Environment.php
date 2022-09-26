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
        return new self(
            new ConsoleOutput(),
            new Downloader(new Filesystem(), HttpClient::create()),
            SystemInfo::detect(),
        );
    }

    public function start(string $rrCommand = null, int $commandTimeout = 10): void
    {
        $this->startTemporalTestServer($commandTimeout);
        $this->startRoadRunner($rrCommand, $commandTimeout);
    }

    public function startTemporalTestServer(int $commandTimeout = 10): void
    {
        if (!$this->downloader->check($this->systemInfo->temporalServerExecutable)) {
            $this->output->write('Download temporal test server... ');
            $this->downloader->download($this->systemInfo);
            $this->output->writeln('<info>done.</info>');
        }

        $temporalPort = parse_url(getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233', PHP_URL_PORT);

        $this->output->write('Starting Temporal test server... ');
        $this->temporalServerProcess = new Process(
            [$this->systemInfo->temporalServerExecutable, $temporalPort, '--enable-time-skipping']
        );
        $this->temporalServerProcess->setTimeout($commandTimeout);
        $this->temporalServerProcess->start();
        $this->output->writeln('<info>done.</info>');
        sleep(1);

        if (!$this->temporalServerProcess->isRunning()) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln('Error starting Temporal server: ' . $this->temporalServerProcess->getErrorOutput());
            exit(1);
        }
    }

    public function startRoadRunner(string $rrCommand = null, int $commandTimeout = 10): void
    {
        $this->roadRunnerProcess = new Process(
            $rrCommand ? explode(' ', $rrCommand) : [$this->systemInfo->rrExecutable, 'serve']
        );
        $this->roadRunnerProcess->setTimeout($commandTimeout);

        $this->output->write('Starting RoadRunner... ');
        $this->roadRunnerProcess->start();

        if (!$this->roadRunnerProcess->isRunning()) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln('Error starting RoadRunner: ' . $this->roadRunnerProcess->getErrorOutput());
            exit(1);
        }

        $roadRunnerStarted = $this->roadRunnerProcess->waitUntil(
            fn($type, $output) => strpos($output, 'RoadRunner server started') !== false
        );

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

        if ($this->roadRunnerProcess !== null && $this->roadRunnerProcess->isRunning()) {
            $this->output->write('Stopping RoadRunner... ');
            $this->roadRunnerProcess->stop();
            $this->output->writeln('<info>done.</info>');
        }
    }
}
