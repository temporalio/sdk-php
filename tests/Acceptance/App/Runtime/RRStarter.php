<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Temporal\Testing\Environment;
use Temporal\Testing\SystemInfo;
use Temporal\Tests\Acceptance\App\Logger\TranscriptStore;

final class RRStarter
{
    private Environment $environment;
    public function __construct(
        private State $runtime,
        ?Environment $environment = null,
    ) {
        $this->environment = $environment ?? Environment::create();
        \register_shutdown_function(fn() => $this->stop());
    }

    /**
     * @param list<class-string> $allowedTestClasses
     */
    public function start(array $allowedTestClasses = []): void
    {
        if ($this->environment->isRoadRunnerRunning()) {
            return;
        }

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

        $this->environment->startRoadRunner(
            rrCommand: $rrCommand,
            envs: $envs,
            configFile: $this->runtime->rrConfigDir . DIRECTORY_SEPARATOR . '.rr.yaml',
        );
    }

    public function stop(): void
    {
        $this->environment->stopRoadRunner();
    }

    public function __destruct()
    {
        $this->stop();
    }
}
