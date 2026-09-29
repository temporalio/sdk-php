<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\Process;

final class Handle
{
    /** @var resource|null */
    private $process = null;

    private int $pid = 0;
    private ?int $exitCode = null;

    /**
     * @param list<string> $command
     */
    public static function start(array $command, string $workingDirectory): self
    {
        $process = \proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => \STDERR, 2 => \STDERR],
            $pipes,
            $workingDirectory,
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException('proc_open() failed for: ' . \implode(' ', $command));
        }

        $handle = new self();
        $handle->process = $process;
        $handle->pid = \proc_get_status($process)['pid'];

        \fclose($pipes[0]);

        return $handle;
    }

    public function pid(): int
    {
        return $this->pid;
    }

    public function exitCode(): ?int
    {
        if ($this->exitCode !== null) {
            return $this->exitCode;
        }

        if ($this->process === null) {
            return null;
        }

        $status = \proc_get_status($this->process);
        if ($status['running']) {
            return null;
        }

        return $this->exitCode = $status['exitcode'];
    }

    public function signal(int $signal): bool
    {
        if ($this->process === null) {
            return false;
        }

        return \proc_terminate($this->process, $signal);
    }

    public function reap(): void
    {
        if ($this->process === null) {
            return;
        }

        $process = $this->process;
        $this->process = null;
        \proc_close($process);
    }
}
