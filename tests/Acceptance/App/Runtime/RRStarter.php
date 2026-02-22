<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Temporal\Testing\Environment;
use Temporal\Testing\SystemInfo;

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

    public function start(): void
    {
        if ($this->environment->isRoadRunnerRunning()) {
            return;
        }

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
        $command = \implode(' ', $rrCommand);

        // echo "\e[1;36mStart RoadRunner with command:\e[0m {$command}\n";
        $this->environment->startRoadRunner(
            rrCommand: $command,
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
