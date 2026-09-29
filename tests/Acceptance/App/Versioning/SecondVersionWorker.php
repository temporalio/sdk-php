<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Versioning;

use Symfony\Component\Process\Process;
use Temporal\Testing\SystemInfo;
use Temporal\Tests\Acceptance\App\Runtime\State;

final class SecondVersionWorker
{
    private const RPC_ADDRESS = 'tcp://127.0.0.1:6002';
    private const START_TIMEOUT_SECONDS = 20;

    private ?Process $process = null;

    public function __construct(
        private readonly State $runtime,
        private readonly string $taskQueue,
        private readonly string $deploymentName,
        private readonly string $buildId,
        private readonly string $workflow,
    ) {}

    public function start(): void
    {
        $configDir = __DIR__;
        $workerArguments = [
            PHP_BINARY,
            $configDir . DIRECTORY_SEPARATOR . 'worker.php',
            'task-queue=' . $this->taskQueue,
            'deployment=' . $this->deploymentName,
            'build-id=' . $this->buildId,
            'workflow=' . $this->workflow,
        ];

        $this->process = new Process(
            command: [
                $this->runtime->workDir . DIRECTORY_SEPARATOR . SystemInfo::detect()->rrExecutable,
                'serve',
                '-w', $configDir,
                '-o', "temporal.namespace={$this->runtime->namespace}",
                '-o', "temporal.address={$this->runtime->address}",
                '-o', 'rpc.listen=' . self::RPC_ADDRESS,
                '-o', 'server.command=' . \implode(',', $workerArguments),
            ],
            env: ['ROADRUNNER_ADDRESS' => self::RPC_ADDRESS],
            timeout: null,
        );

        $this->process->start();

        $deadline = \microtime(true) + self::START_TIMEOUT_SECONDS;
        while (\microtime(true) < $deadline) {
            if (\str_contains($this->process->getOutput(), 'RoadRunner server started')) {
                return;
            }

            if (!$this->process->isRunning()) {
                throw new \RuntimeException(
                    'Second version worker died: ' . $this->process->getOutput() . $this->process->getErrorOutput(),
                );
            }

            \usleep(200_000);
        }

        throw new \RuntimeException('Second version worker did not start in time');
    }

    public function stop(): void
    {
        $this->process?->stop(timeout: 10);
        $this->process = null;
    }
}
