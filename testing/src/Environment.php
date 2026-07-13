<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
use Temporal\Common\SearchAttributes\ValueType;
use Temporal\Testing\Support\TestOutputStyle;

final class Environment
{
    public readonly Command $command;
    public readonly SymfonyStyle $io;
    private ?Process $temporalTestServerProcess = null;
    private ?Process $temporalServerProcess = null;
    private ?Process $roadRunnerProcess = null;
    private bool $externalTemporalProcessActive = false;

    public function __construct(
        OutputInterface $output,
        private Downloader $downloader,
        private SystemInfo $systemInfo,
        ?Command $command = null,
        private bool $allowExternalTemporalProcess = false,
    ) {
        $this->io = $output instanceof SymfonyStyle
            ? $output
            : new SymfonyStyle(new ArgvInput(), $output);
        $this->command = $command ?? Command::fromEnv();
    }

    public static function create(?Command $command = null, ?SystemInfo $systemInfo = null): self
    {
        $token = \getenv('GITHUB_TOKEN');
        $allowExternalTemporalProcess = \getenv('ALLOW_EXTERNAL_TEMPORAL_PROCESS') === 'true';

        $systemInfo ??= SystemInfo::detect();

        return new self(
            new TestOutputStyle(new ArgvInput(), new ConsoleOutput()),
            new Downloader(new Filesystem(), HttpClient::create([
                'headers' => [
                    'authorization' => \is_string($token) ? 'token ' . $token : null,
                ],
            ])),
            $systemInfo,
            $command,
            $allowExternalTemporalProcess,
        );
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
        $temporalServerAddress = $this->command->address;
        if ($temporalServerAddress === null) {
            $this->io->error('Temporal server address is not set.');
            exit(1);
        }
        $temporalHost = \parse_url($temporalServerAddress, PHP_URL_HOST);
        $temporalPort = \parse_url($temporalServerAddress, PHP_URL_PORT);

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

        $process = new Process([
            $this->systemInfo->temporalCliExecutable,
            "server", "start-dev",
            "--port", $temporalPort,
            '--log-level', 'error',
            '--ip', $temporalHost,
            ...$parameters,
        ]);
        $process->setTimeout($commandTimeout);
        $this->temporalServerProcess = $process;

        $this->runProcess('Temporal', $process, $commandTimeout, function () use ($temporalServerAddress): bool {
            $check = new Process([
                $this->systemInfo->temporalCliExecutable,
                'operator',
                'cluster',
                'health',
                '--address', $temporalServerAddress,
            ]);
            $check->setTimeout(1);
            $check->run();

            return \str_contains($check->getOutput(), 'SERVING');
        }, onFailure: function (Process $process): void {
            $errorOutput = $process->getErrorOutput();

            if (!$this->allowExternalTemporalProcess || !\str_contains($errorOutput, 'address already in use')) {
                $this->io->error([
                    \sprintf('Error starting Temporal server: %s.', $errorOutput ?: "Health check failed"),
                    \sprintf('Command: `%s`.', $this->serializeProcess($process)),
                ]);
                exit(1);
            }
            $this->io->warning('Using external Temporal Server');
            $this->externalTemporalProcessActive = true;
        });
    }

    public function startTemporalTestServer(int $commandTimeout = 10): void
    {
        $temporalPort = \parse_url((string) $this->command->address, PHP_URL_PORT);

        $process = new Process([$this->systemInfo->temporalServerExecutable, $temporalPort, '--enable-time-skipping']);
        $process->setTimeout($commandTimeout);
        $this->temporalTestServerProcess = $process;

        $this->runProcess('Temporal Test', $process, $commandTimeout, static function (): bool {
            \sleep(1);

            return true;
        }, onFailure: function (Process $process): void {
            $errorOutput = $process->getErrorOutput();

            if (!$this->allowExternalTemporalProcess || !\str_contains($errorOutput, 'address already in use')) {
                $this->io->error([
                    \sprintf('Error starting Temporal Test server: %s.', $errorOutput),
                    \sprintf('Command: `%s`.', $this->serializeProcess($process)),
                ]);
                exit(1);
            }
            $this->io->warning('Using external Temporal Test Server');
            $this->externalTemporalProcessActive = true;
        });
    }

    /**
     * @param array<string, mixed> $envs
     */
    public function startRoadRunner(array $rrCommand, int $commandTimeout = 10, array $envs = [], string $configFile = '.rr.yaml'): void
    {
        if (!$this->isTemporalRunning() && !$this->isTemporalTestRunning()) {
            $this->io->error([
                'Temporal server is not running. Please start it before starting RoadRunner.',
            ]);
            exit(1);
        }

        $process = new Process(command: $rrCommand, env: $envs, timeout: $commandTimeout);
        $this->roadRunnerProcess = $process;

        $this->runProcess('RoadRunner', $process, $commandTimeout, function () use ($process, $configFile) {
            $output = $process->getOutput();
            if (!\str_contains($output, 'RoadRunner server started')) {
                return false;
            }

            $check = new Process([$this->systemInfo->rrExecutable, 'workers', '-c', $configFile]);
            $check->setTimeout(1);
            $check->run();

            return \str_contains($check->getOutput(), 'Workers of');
        });
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

        $this->io->info('Executing Temporal Command: ' . $this->serializeProcess($process));

        $process->run();
    }

    public function stopTemporalServer(): void
    {
        if ($this->isTemporalRunning()) {
            $this->io->info('Stopping Temporal server... ');
            $this->stopTemporalServerProcess();
            $this->io->info('Temporal server stopped.');
        }
    }

    public function stopTemporalTestServer(): void
    {
        if ($this->isTemporalTestRunning()) {
            $this->io->info('Stopping Temporal Test server... ');
            $this->stopTemporalTestServerProcess();
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

    /**
     * @psalm-assert Process $this->temporalServerProcess
     */
    public function isTemporalRunning(): bool
    {
        return ($this->allowExternalTemporalProcess && $this->externalTemporalProcessActive) ||
            $this->temporalServerProcess?->isRunning() === true;
    }

    /**
     * @psalm-assert Process $this->roadRunnerProcess
     */
    public function isRoadRunnerRunning(): bool
    {
        return $this->roadRunnerProcess?->isRunning() === true;
    }

    /**
     * @psalm-assert Process $this->temporalTestServerProcess
     */
    public function isTemporalTestRunning(): bool
    {
        return ($this->allowExternalTemporalProcess && $this->externalTemporalProcessActive) ||
            $this->temporalTestServerProcess?->isRunning() === true;
    }

    private function runProcess(string $name, Process $process, int $commandTimeout, callable $readiness, ?callable $onFailure = null): void
    {
        $this->io->info(\sprintf("Starting %s server...", $name));
        $this->io->info('Running command: ' . $this->serializeProcess($process));
        $process->start();

        $deadline = \microtime(true) + (float) $commandTimeout;

        while ($process->isRunning() && \microtime(true) < $deadline) {
            if ($readiness()) {
                break;
            }
            \usleep(10_000);
        }

        if (!$process->isRunning()) {
            ($onFailure ?? function (Process $process) use ($name): void {
                $this->io->error(\sprintf(
                    'Failed to start until %s is ready. Status: "%s". Stderr: "%s". Stdout: "%s".',
                    $name,
                    $process->getStatus(),
                    $process->getErrorOutput(),
                    $process->getOutput(),
                ));
                $this->io->writeln(\sprintf(
                    "Command: `%s`.",
                    $this->serializeProcess($process),
                ));
                exit(1);
            })($process);
        }

        $this->io->info(\sprintf("%s server started.", $name));
    }

    private function stopTemporalTestServerProcess(): void
    {
        if ($this->externalTemporalProcessActive) {
            $this->externalTemporalProcessActive = false;
            return;
        }
        $this->temporalTestServerProcess->stop();
        $this->temporalTestServerProcess = null;
    }

    private function stopTemporalServerProcess(): void
    {
        if ($this->externalTemporalProcessActive) {
            $this->externalTemporalProcessActive = false;
            return;
        }
        $this->temporalServerProcess->stop();
        $this->temporalServerProcess = null;
    }

    private function serializeProcess(?Process $process): string
    {
        if ($process === null) {
            return 'process is not started';
        }
        $reflection = new \ReflectionClass($process);
        $reflectionProperty = $reflection->getProperty('commandline');
        $commandLine = $reflectionProperty->getValue($process);
        return \implode(' ', $commandLine);
    }
}
