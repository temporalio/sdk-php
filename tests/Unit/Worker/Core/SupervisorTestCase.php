<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\Worker\Core\CoreRole;
use Temporal\Worker\Core\Supervisor;

#[CoversClass(Supervisor::class)]
#[RequiresPhpExtension('pcntl')]
#[RequiresPhpExtension('posix')]
#[RequiresPhpExtension('ffi')]
final class SupervisorTestCase extends TestCase
{
    private const LONG_RUN_SECONDS = 30;

    public function testInitialChildCrashAtStartupStopsTheOthers(): void
    {
        $started = 0;
        $supervisor = $this->supervisor(function () use (&$started): int {
            return ++$started === 1 ? $this->child(3, 0.0) : $this->child(0, self::LONG_RUN_SECONDS);
        });

        $code = $supervisor->run([CoreRole::Workflow, CoreRole::Activity]);

        $this->assertSame(2, $started);
        $this->assertGreaterThanOrEqual(3, $code);
    }

    public function testChildThatCrashesAfterStartupIsRestarted(): void
    {
        $started = 0;
        $supervisor = $this->supervisor(function () use (&$started): int {
            if (++$started === 1) {
                return $this->child(5, 1.2);
            }
            \posix_kill(\getmypid(), \SIGTERM);

            return $this->child(0, self::LONG_RUN_SECONDS);
        });

        $code = $supervisor->run([CoreRole::Activity]);

        $this->assertSame(2, $started);
        $this->assertSame(128 + \SIGTERM, $code);
    }

    public function testChildThatIgnoresSigtermIsKilledAfterTheStopTimeout(): void
    {
        $supervisor = $this->supervisor(function (): int {
            $pid = $this->child(0, self::LONG_RUN_SECONDS, ignoreSigterm: true);
            \posix_kill(\getmypid(), \SIGTERM);

            return $pid;
        }, stopTimeoutSeconds: 0.3);

        $startedAt = \microtime(true);
        $code = $supervisor->run([CoreRole::Activity]);

        $this->assertSame(128 + \SIGKILL, $code);
        $this->assertLessThan(5, \microtime(true) - $startedAt);
    }

    public function testStartFailureStopsTheRunningChildrenAndIsRethrown(): void
    {
        $started = 0;
        $supervisor = $this->supervisor(function () use (&$started): int {
            if (++$started === 2) {
                throw new \RuntimeException('no more processes');
            }

            return $this->child(0, self::LONG_RUN_SECONDS);
        });

        $this->expectExceptionMessage('no more processes');
        $supervisor->run([CoreRole::Workflow, CoreRole::Activity]);
    }

    /**
     * @param \Closure(CoreRole): int $start
     */
    private function supervisor(\Closure $start, float $stopTimeoutSeconds = 5.0): Supervisor
    {
        return new Supervisor($start, static function (int $pid): void {}, new NullLogger(), $stopTimeoutSeconds);
    }

    private function child(int $exitCode, float $seconds, bool $ignoreSigterm = false): int
    {
        \pcntl_sigprocmask(\SIG_BLOCK, [\SIGTERM, \SIGINT]);
        $pid = \pcntl_fork();
        if ($pid !== 0) {
            \pcntl_sigprocmask(\SIG_UNBLOCK, [\SIGTERM, \SIGINT]);
            return $pid;
        }

        \pcntl_signal(\SIGTERM, $ignoreSigterm ? \SIG_IGN : \SIG_DFL);
        \pcntl_signal(\SIGINT, \SIG_DFL);
        \pcntl_sigprocmask(\SIG_UNBLOCK, [\SIGTERM, \SIGINT]);
        \usleep((int) ($seconds * 1_000_000));
        \FFI::cdef('void _exit(int status);')->_exit($exitCode);
    }
}
