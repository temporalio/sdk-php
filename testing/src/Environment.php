<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
use Temporal\Common\SearchAttributes\ValueType;

final class Environment
{
    /**
     * @readonly
     */
    public Command $command;

    private Downloader $downloader;
    private Output $output;
    private SystemInfo $systemInfo;
    private ?Process $temporalTestServerProcess = null;
    private ?Process $temporalServerProcess = null;
    private ?Process $roadRunnerProcess = null;

    public function __construct(
        Output $output,
        Downloader $downloader,
        SystemInfo $systemInfo,
        Command $command,
    ) {
        $this->downloader = $downloader;
        $this->systemInfo = $systemInfo;
        $this->output = $output;
        $this->command = $command;
    }

    public static function create(?Command $command = null): self
    {
        $token = \getenv('GITHUB_TOKEN');

        $info = SystemInfo::detect();
        \is_string(\getenv('ROADRUNNER_BINARY')) and $info->rrExecutable = \getenv('ROADRUNNER_BINARY');

        return new self(
            new ConsoleOutput(),
            new Downloader(new Filesystem(), HttpClient::create([
                'headers' => [
                    'authorization' => $token ? 'token ' . $token : null,
                ],
            ])),
            $info,
            $command ?? Command::fromEnv(),
        );
    }

    /**
     * @param array<string, mixed> $envs
     */
    public function start(?string $rrCommand = null, int $commandTimeout = 10, array $envs = []): void
    {
        $this->startTemporalTestServer($commandTimeout);
        $this->startRoadRunner($rrCommand, $commandTimeout, $envs);
    }

    /**
     * @param list<non-empty-string> $parameters
     * @param array<non-empty-string, ValueType|non-empty-string> $searchAttributes Key is the name of the search
     *        attribute, value is the type of the search attribute. Expected values from {@see ValueType}.
     */
    public function startTemporalServer(
        int $commandTimeout = 10,
        array $parameters = [],
        array $searchAttributes = [],
    ): void {
        $temporalHost = \parse_url($this->command->address, PHP_URL_HOST);
        $temporalPort = \parse_url($this->command->address, PHP_URL_PORT);

        // Add search attributes
        foreach ($searchAttributes as $name => $type) {
            $type = \is_string($type) ? ValueType::tryFrom($type) : $type;
            if (!$type instanceof ValueType) {
                \trigger_error('Invalid search attribute type: ' . \get_debug_type($type), E_USER_WARNING);
                continue;
            }

            if (\preg_match('/^[a-zA-Z0-9_-]+$/', $name) !== 1) {
                \trigger_error('Invalid search attribute name: ' . $name, E_USER_WARNING);
                continue;
            }

            $parameters[] = '--search-attribute';
            $parameters[] = $name . '=' . match ($type) {
                ValueType::Bool => 'bool',
                ValueType::Float => 'double',
                ValueType::Int => 'int',
                ValueType::Keyword => 'keyword',
                ValueType::KeywordList => 'keywordList',
                ValueType::Text => 'text',
                ValueType::Datetime => 'datetime',
            };
        }

        $this->output->write('Starting Temporal server... ');
        $this->temporalServerProcess = new Process(
            [
                $this->systemInfo->temporalCliExecutable,
                "server", "start-dev",
                "--port", $temporalPort,
                '--log-level', 'error',
                '--ip', $temporalHost,
                '--headless',
                ...$parameters,
            ],
        );
        $this->temporalServerProcess->setTimeout($commandTimeout);
        $temporalStarted = false;
        //        $this->output->writeln('Running command: ' . $this->temporalServerProcess->getCommandLine());
        $this->temporalServerProcess->start(function ($type, $output) use (&$temporalStarted): void {
            if ($type === Process::OUT && \str_contains($output, 'Server: ')) {
                $check = new Process([
                    $this->systemInfo->temporalCliExecutable,
                    'operator',
                    'cluster',
                    'health',
                    '--address', $this->command->address,
                ]);
                $check->run();
                if (\str_contains($check->getOutput(), 'SERVING')) {
                    $temporalStarted = true;
                }
            }
        });

        $deadline = \microtime(true) + $commandTimeout;
        while (!$temporalStarted && \microtime(true) < $deadline) {
            \usleep(50_000);
            if (!$temporalStarted) {
                $check = new Process([$this->systemInfo->temporalCliExecutable, 'operator', 'cluster', 'health']);
                $check->run();
                if (\str_contains($check->getOutput(), 'SERVING')) {
                    $temporalStarted = true;
                }
            }
        }

        if (!$temporalStarted || !$this->temporalServerProcess->isRunning()) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln(\sprintf(
                "Error starting Temporal server: %s.\r\nCommand: `%s`.",
                !$temporalStarted ? "Health check failed" : $this->temporalServerProcess->getErrorOutput(),
                $this->temporalServerProcess->getCommandLine(),
            ));
            exit(1);
        }
        $this->output->writeln('<info>done.</info>');
    }

    public function startTemporalTestServer(int $commandTimeout = 10): void
    {
        if (!$this->downloader->check($this->systemInfo->temporalServerExecutable)) {
            $this->output->write('Download temporal test server... ');
            $this->downloader->download($this->systemInfo);
            $this->output->writeln('<info>done.</info>');
        }

        $temporalPort = \parse_url($this->command->address, PHP_URL_PORT);

        $this->output->write('Starting Temporal test server... ');
        $this->temporalTestServerProcess = new Process(
            [$this->systemInfo->temporalServerExecutable, $temporalPort, '--enable-time-skipping'],
        );
        $this->temporalTestServerProcess->setTimeout($commandTimeout);
        $this->temporalTestServerProcess->start();

        \sleep(1);

        if (!$this->temporalTestServerProcess->isRunning()) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln(\sprintf(
                "Error starting Temporal Test server: %s.\r\nCommand: `%s`.",
                $this->temporalTestServerProcess->getErrorOutput(),
                $this->temporalTestServerProcess->getCommandLine(),
            ));
            exit(1);
        }
        $this->output->writeln('<info>done.</info>');
    }

    /**
     * @param array<string, mixed> $envs
     */
    public function startRoadRunner(?string $rrCommand = null, int $commandTimeout = 10, array $envs = []): void
    {
        $this->roadRunnerProcess = new Process(
            command: $rrCommand ? \explode(' ', $rrCommand) : [$this->systemInfo->rrExecutable, 'serve'],
            env: $envs,
        );
        $this->roadRunnerProcess->setTimeout($commandTimeout);

        $this->output->write('Starting RoadRunner... ');
        $roadRunnerStarted = false;
        //        $this->output->writeln('Running command: ' . $this->roadRunnerProcess->getCommandLine());
        $this->roadRunnerProcess->start(static function ($type, $output) use (&$roadRunnerStarted): void {
            if ($type === Process::OUT && \str_contains($output, 'RoadRunner server started')) {
                $roadRunnerStarted = true;
            }
        });

        if (!$this->roadRunnerProcess->isRunning()) {
            $this->output->writeln('<error>error</error>');
            $this->output->writeln(\sprintf(
                "Error starting RoadRunner: %s.\r\nCommand: `%s`.",
                $this->roadRunnerProcess->getErrorOutput(),
                $this->roadRunnerProcess->getCommandLine(),
            ));
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
            $this->output->writeln(\sprintf(
                "Error starting RoadRunner: %s.\r\nCommand: `%s`.",
                $this->roadRunnerProcess->getErrorOutput(),
                $this->roadRunnerProcess->getCommandLine(),
            ));
            exit(1);
        }

        $this->output->writeln('<info>done.</info>');
    }

    public function stop(): void
    {
        $this->stopRoadRunner();
        $this->stopTemporalTestServer();
        $this->stopTemporalServer();
    }

    public function executeTemporalCommand(array|string $command, int $timeout = 10): void
    {
        $command = \array_merge(
            [$this->systemInfo->temporalCliExecutable],
            (array) $command,
        );

        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();
    }

    public function stopTemporalServer(): void
    {
        if ($this->isTemporalRunning()) {
            $this->output->write('Stopping Temporal server... ');
            $this->temporalServerProcess->stop();
            $this->output->writeln('<info>done.</info>');
        }
    }

    public function stopTemporalTestServer(): void
    {
        if ($this->isTemporalTestRunning()) {
            $this->output->write('Stopping Temporal Test server... ');
            $this->temporalTestServerProcess->stop();
            $this->output->writeln('<info>done.</info>');
        }
    }

    public function stopRoadRunner(): void
    {
        if ($this->isRoadRunnerRunning()) {
            $this->output->write('Stopping RoadRunner... ');
            $this->roadRunnerProcess->stop();
            $this->output->writeln('<info>done.</info>');
        }
    }

    public function isTemporalRunning(): bool
    {
        return $this->temporalServerProcess?->isRunning() === true;
    }

    public function isRoadRunnerRunning(): bool
    {
        return $this->roadRunnerProcess?->isRunning() === true;
    }

    public function isTemporalTestRunning(): bool
    {
        return $this->temporalTestServerProcess?->isRunning() === true;
    }
}
