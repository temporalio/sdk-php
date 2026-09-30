<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Symfony\Component\Process\Process;
use Temporal\Testing\Environment;
use Temporal\Testing\SystemInfo;
use Temporal\Testing\Transcript\TranscriptStore;

final class RRStarter
{
    private Environment $environment;
    private ?Process $coreWorker = null;

    public function __construct(
        private State $runtime,
        ?Environment $environment = null,
    ) {
        $this->environment = $environment ?? Environment::create();
        \register_shutdown_function(fn() => $this->stop());
    }

    public function start(): void
    {
        if ($this->environment->isRoadRunnerRunning() || $this->coreWorker?->isRunning()) {
            return;
        }

        $allowedTestClasses = $this->runtime->allowedTestClasses;

        $systemInfo = SystemInfo::detect();
        $run = $this->runtime->command;

        $workerArgs = [
            PHP_BINARY,
            ...$run->getPhpBinaryArguments(),
            $this->runtime->rrConfigDir . DIRECTORY_SEPARATOR . 'worker.php',
            ...$run->getCommandLineArguments(),
        ];

        foreach ($allowedTestClasses as $class) {
            $workerArgs[] = 'test-class=' . $class;
        }

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
            'server.command=' . \implode(',', $workerArgs),
        ];
        if ($run->tlsKey !== null) {
            $rrCommand[] = '-o';
            $rrCommand[] = "tls.key={$run->tlsKey}";
        }
        if ($run->tlsCert !== null) {
            $rrCommand[] = '-o';
            $rrCommand[] = "tls.cert={$run->tlsCert}";
        }

        $envs = [];
        $runId = \getenv('TEMPORAL_TRANSCRIPT_RUN_ID');
        if (\is_string($runId) && $runId !== '') {
            $envs['TEMPORAL_TRANSCRIPT_RUN_ID'] = $runId;
        }

        if (\getenv('TEMPORAL_WORKER_TRANSPORT') === 'core') {
            $this->startCoreWorker($workerArgs, $envs);
            return;
        }

        $this->environment->startRoadRunner(
            rrCommand: $rrCommand,
            envs: $envs,
            configFile: $this->runtime->rrConfigDir . DIRECTORY_SEPARATOR . '.rr.yaml',
        );
    }

    public function stop(): void
    {
        if ($this->coreWorker?->isRunning()) {
            \exec('pgrep -P ' . $this->coreWorker->getPid(), $children);
            $this->coreWorker->stop(3);
            foreach ($children as $child) {
                \posix_kill((int) $child, \SIGKILL);
            }
        }
        $this->coreWorker = null;
        $this->environment->stopRoadRunner();
    }

    private function startCoreWorker(array $workerArgs, array $envs): void
    {
        $log = $this->runtime->workDir . '/runtime/tests/core-worker.log';
        @\mkdir(\dirname($log), recursive: true);
        $command = \implode(' ', \array_map(\escapeshellarg(...), $workerArgs));
        $this->coreWorker = Process::fromShellCommandline(
            \sprintf('exec %s >> %s 2>&1', $command, \escapeshellarg($log)),
            $this->runtime->rrConfigDir,
            [
                'TEMPORAL_CORE_WORKFLOW_PROCESSES' => 1,
                'TEMPORAL_CORE_ACTIVITY_PROCESSES' => $this->runtime->activityWorkers,
                'TEMPORAL_TRANSCRIPT_DIR' => 'runtime/tests/transcripts',
            ] + $envs,
            timeout: null,
        );
        $this->coreWorker->start();
        \usleep(500_000);
        if (!$this->coreWorker->isRunning()) {
            throw new \RuntimeException("Core worker exited, see $log");
        }
    }

    public function __destruct()
    {
        $this->stop();
    }
}
