<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Temporal\Testing\Environment;
use Temporal\Testing\SystemInfo;

final class RRStarter
{
    private Environment $environment;

    /** @var list<int> stdout offsets at test boundaries; max 2 entries (previous + current test) */
    private array $stdoutMarkers = [];

    /** @var list<int> stderr offsets at test boundaries; max 2 entries (previous + current test) */
    private array $stderrMarkers = [];

    public function __construct(
        private State $runtime,
        ?Environment $environment = null,
    ) {
        $this->environment = $environment ?? Environment::create();
        \register_shutdown_function(fn() => $this->stop());
    }

    public function start(): void
    {
        if ($this->environment->isRoadRunnerRunning()) {
            return;
        }

        $this->stdoutMarkers = [];
        $this->stderrMarkers = [];

        $systemInfo = SystemInfo::detect();
        $run = $this->runtime->command;

        $rrCommand = [
            $this->runtime->workDir . DIRECTORY_SEPARATOR . $systemInfo->rrExecutable,
            'serve',
            '-w',
            $this->runtime->rrConfigDir,
            '-o',
            "temporal.namespace={$this->runtime->namespace}",
            '-o',
            "temporal.address={$this->runtime->address}",
            '-o',
            'server.command=' . \implode(',', [
                PHP_BINARY,
                ...$run->getPhpBinaryArguments(),
                $this->runtime->rrConfigDir . DIRECTORY_SEPARATOR . 'worker.php',
                ...$run->getCommandLineArguments(),
            ]),
        ];
        $run->tlsKey === null or $rrCommand = [...$rrCommand, '-o', "tls.key={$run->tlsKey}"];
        $run->tlsCert === null or $rrCommand = [...$rrCommand, '-o', "tls.cert={$run->tlsCert}"];

        // echo "\e[1;36mStart RoadRunner with command:\e[0m {$command}\n";
        $this->environment->startRoadRunner(
            rrCommand: $rrCommand,
            configFile: $this->runtime->rrConfigDir . DIRECTORY_SEPARATOR . '.rr.yaml',
        );
    }

    public function stop(): void
    {
        $this->environment->stopRoadRunner();
    }

    public function getOutput(): string
    {
        return $this->environment->getRoadRunnerOutput();
    }

    public function getErrorOutput(): string
    {
        return $this->environment->getRoadRunnerErrorOutput();
    }

    public function markTestBoundary(): void
    {
        $this->stdoutMarkers[] = \strlen($this->environment->getRoadRunnerOutput());
        $this->stderrMarkers[] = \strlen($this->environment->getRoadRunnerErrorOutput());
        if (\count($this->stdoutMarkers) > 2) {
            \array_shift($this->stdoutMarkers);
            \array_shift($this->stderrMarkers);
        }
    }

    public function getOutputSinceLastTwoTests(): string
    {
        return \substr($this->environment->getRoadRunnerOutput(), $this->stdoutMarkers[0] ?? 0);
    }

    public function getErrorOutputSinceLastTwoTests(): string
    {
        return \substr($this->environment->getRoadRunnerErrorOutput(), $this->stderrMarkers[0] ?? 0);
    }

    public function __destruct()
    {
        $this->stop();
    }
}
