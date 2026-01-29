<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Temporal\Testing\Environment;
use Temporal\Testing\SystemInfo;

final class RRStarter
{
    public function __construct(
        private State $runtime,
        private Environment $environment,
    ) {
        \register_shutdown_function(fn() => $this->stop());
    }

    public function start(): void
    {
        if ($this->environment->isRoadRunnerRunning()) {
            return;
        }

        $sysInfo = SystemInfo::detect();
        $run = $this->runtime->command;

        $rrCommand = [
            $this->runtime->workDir . DIRECTORY_SEPARATOR . $sysInfo->rrExecutable,
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
        $command = \implode(' ', $rrCommand);

        // echo "\e[1;36mStart RoadRunner with command:\e[0m {$command}\n";
        $this->environment->startRoadRunner($command);
    }

    public function stop(): void
    {
        if ($this->environment->isRoadRunnerRunning()) {
            // echo "\e[1;36mStop RoadRunner\e[0m\n";
            $this->environment->stop();
        }
    }

    public function __destruct()
    {
        $this->stop();
    }
}
