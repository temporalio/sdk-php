<?php

declare(strict_types=1);

namespace Temporal\Tests;

use Symfony\Component\Process\Process;

final class CoreWorker
{
    private const STARTUP_CHECK_MICROSECONDS = 500_000;

    public static function enabled(): bool
    {
        return \getenv('TEMPORAL_WORKER_TRANSPORT') === 'core';
    }

    /**
     * @param list<string> $command
     * @param array<string, string|int> $env
     */
    public static function start(array $command, string $cwd, string $log, array $env): Process
    {
        @\mkdir(\dirname($log), recursive: true);
        $process = Process::fromShellCommandline(
            \sprintf('exec %s >> %s 2>&1', \implode(' ', \array_map(\escapeshellarg(...), $command)), \escapeshellarg($log)),
            $cwd,
            $env,
            timeout: null,
        );
        $process->start();
        \usleep(self::STARTUP_CHECK_MICROSECONDS);
        if (!$process->isRunning()) {
            throw new \RuntimeException("The core worker exited, see $log");
        }

        return $process;
    }
}
