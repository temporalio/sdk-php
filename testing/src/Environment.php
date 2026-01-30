<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
use Temporal\Common\SearchAttributes\ValueType;
use Temporal\Testing\Support\TestOutputStyle;

final class Environment
{
    private ?Process $temporalTestServerProcess = null;
    private ?Process $temporalServerProcess = null;
    private ?Process $roadRunnerProcess = null;

    public function __construct(
        private SymfonyStyle $io,
        private Downloader $downloader,
        private SystemInfo $systemInfo,
        public readonly Command $command,
    ) {}

    public static function create(?Command $command = null): self
    {
        $token = \getenv('GITHUB_TOKEN');

        $systemInfo = SystemInfo::detect();
        \is_string(\getenv('ROADRUNNER_BINARY')) and $systemInfo->rrExecutable = \getenv('ROADRUNNER_BINARY');

        return new self(
            new TestOutputStyle(new ArgvInput(), new ConsoleOutput()),
            new Downloader(new Filesystem(), HttpClient::create([
                'headers' => [
                    'authorization' => $token ? 'token ' . $token : null,
                ],
            ])),
            $systemInfo,
            $command ?? Command::fromEnv(),
        );
    }

    /**
     * @param array<string, mixed> $envs
     */
    public function start(string $roadRunnerConfigFile, ?array $rrCommand = null, int $commandTimeout = 10, array $envs = []): void
    {
        $this->startTemporalTestServer($commandTimeout);
        $this->startRoadRunner($roadRunnerConfigFile, $rrCommand, $commandTimeout, $envs);
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

        $this->io->info('Starting Temporal server... ');
        $this->temporalServerProcess = new Process(
            [
                $this->systemInfo->temporalCliExecutable,
                "server", "start-dev",
                "--port", $temporalPort,
                '--log-level', 'error',
                '--ip', $temporalHost,
                //                '--headless',
                ...$parameters,
            ],
        );
        $this->temporalServerProcess->setTimeout($commandTimeout);
        $temporalStarted = false;
        $this->io->info('Running command: ' . $this->serializeProcess($this->temporalServerProcess));
        $this->temporalServerProcess->start();

        $deadline = \microtime(true) + $commandTimeout;
        while (!$temporalStarted && \microtime(true) < $deadline) {
            \usleep(10_000);
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

        if (!$temporalStarted || !$this->temporalServerProcess->isRunning()) {
            $this->io->error([
                \sprintf(
                    'Error starting Temporal server: %s.',
                    !$temporalStarted ? "Health check failed" : $this->temporalServerProcess->getErrorOutput(),
                ),
                \sprintf(
                    'Command: `%s`.',
                    $this->serializeProcess($this->temporalServerProcess),
                ),
            ]);
            exit(1);
        }
        $this->io->info('Temporal server started.');
    }

    public function startTemporalTestServer(int $commandTimeout = 10): void
    {
        if (!$this->downloader->check($this->systemInfo->temporalServerExecutable)) {
            $this->io->info('Download Temporal test server... ');
            $this->downloader->download($this->systemInfo);
            $this->io->info('Temporal test server downloaded.');
        }

        $temporalPort = \parse_url($this->command->address, PHP_URL_PORT);

        $this->io->info('Starting Temporal test server... ');
        $this->temporalTestServerProcess = new Process(
            [$this->systemInfo->temporalServerExecutable, $temporalPort, '--enable-time-skipping'],
        );
        $this->temporalTestServerProcess->setTimeout($commandTimeout);
        $this->temporalTestServerProcess->start();

        \sleep(1);

        if (!$this->temporalTestServerProcess->isRunning()) {
            $this->io->error([
                \sprintf(
                    'Error starting Temporal Test server: %s.',
                    $this->temporalTestServerProcess->getErrorOutput(),
                ),
                \sprintf(
                    'Command: `%s`.',
                    $this->serializeProcess($this->temporalTestServerProcess),
                ),
            ]);
            exit(1);
        }
        $this->io->info('Temporal Test server started.');
    }

    /**
     * @param array<string, mixed> $envs
     */
    public function startRoadRunner(string $configFile, ?array $parameters = null, int $commandTimeout = 10, array $envs = []): void
    {
        $this->roadRunnerProcess = new Process(
            command: [
                $this->systemInfo->rrExecutable,
                "serve",
                '-c', $configFile,
                ...$parameters,
            ],
            env: $envs,
        );
        $this->roadRunnerProcess->setTimeout($commandTimeout);

        $this->io->info('Starting RoadRunner... ');
        $roadRunnerStarted = false;
        $this->io->info('Running command: ' . $this->serializeProcess($this->roadRunnerProcess));
        $this->roadRunnerProcess->start();

        // wait for roadrunner to start
        $deadline = \microtime(true) + $commandTimeout;
        while (!$roadRunnerStarted && \microtime(true) < $deadline) {
            \usleep(10_000);
            $check = new Process([$this->systemInfo->rrExecutable, 'workers', '-c', $configFile]);
            $check->run();
            if (\str_contains($check->getOutput(), 'Workers of')) {
                $roadRunnerStarted = true;
            }
        }

        if (!$roadRunnerStarted) {
            $this->io->error(\sprintf(
                'Failed to start until RoadRunner is ready. Status: "%s". Stderr: "%s". Stdout: "%s".',
                $this->roadRunnerProcess->getStatus(),
                $this->roadRunnerProcess->getErrorOutput(),
                $this->roadRunnerProcess->getOutput(),
            ));
            $this->io->writeln(\sprintf(
                "Command: `%s`.",
                $this->serializeProcess($this->roadRunnerProcess),
            ));
            exit(1);
        }

        $this->io->info('RoadRunner server started.');
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
            $this->io->info('Stopping Temporal server... ');
            $this->temporalServerProcess->stop();
            $this->temporalServerProcess = null;
            $this->io->info('Temporal server stopped.');
        }
    }

    public function stopTemporalTestServer(): void
    {
        if ($this->isTemporalTestRunning()) {
            $this->io->info('Stopping Temporal Test server... ');
            $this->temporalTestServerProcess->stop();
            $this->temporalTestServerProcess = null;
            $this->io->info('Temporal Test server stopped.');
        }
    }

    public function stopRoadRunner(): void
    {
        if ($this->isRoadRunnerRunning()) {
            $this->io->info('Stopping RoadRunner... ');
            $this->roadRunnerProcess->stop();
            $this->roadRunnerProcess = null;
            $this->io->info('RoadRunner server stopped.');
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

    private function serializeProcess(?Process $temporalServerProcess): string|array
    {
        $reflection = new \ReflectionClass($temporalServerProcess);
        $reflectionProperty = $reflection->getProperty('commandline');
        $commandLine = $reflectionProperty->getValue($temporalServerProcess);
        return \implode(' ', $commandLine);
    }
}
