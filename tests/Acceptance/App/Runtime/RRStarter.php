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

        $run = $this->runtime->command;

        $configFile = $this->runtime->rrConfigDir . DIRECTORY_SEPARATOR . '.rr.yaml';

        $parameters = [
            '-w',
            $this->runtime->rrConfigDir,
            '-o',
            "temporal.namespace={$this->runtime->namespace}",
            '-o',
            "temporal.address={$this->runtime->address}",
            '-o',
            "temporal.activities.num_workers={$this->runtime->activityWorkers}",
            '-o',
            'server.command=' . \implode(',', [
                PHP_BINARY,
                ...$run->getPhpBinaryArguments(),
                $this->runtime->rrConfigDir . DIRECTORY_SEPARATOR . 'worker.php',
                ...$run->getCommandLineArguments(),
            ]),
        ];
        $run->tlsKey === null or $parameters = [...$parameters, '-o', "tls.key={$run->tlsKey}"];
        $run->tlsCert === null or $parameters = [...$parameters, '-o', "tls.cert={$run->tlsCert}"];

        $this->environment->startRoadRunner($configFile, $parameters);
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
