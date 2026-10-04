<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Worker;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\Tests\Unit\Client\Stub\LoggerSpy;
use Temporal\Worker\Core\ChildProcesses;
use Temporal\Worker\Core\CoreRole;

final class ChildProcessesTestCase extends TestCase
{
    private const CHILD_SCRIPT = __DIR__ . '/../../Fixtures/core-child.php';
    private const SERVE_EXIT_CODE = 7;
    private const ACTIVITY_EXIT_CODE = 3;
    private const WORKFLOW_EXIT_CODE = 4;

    #[RunInSeparateProcess]
    public function testForkedChildExitsWithTheServeCode(): void
    {
        $children = new ChildProcesses(static fn(): int => self::SERVE_EXIT_CODE, new NullLogger(), true, []);

        self::assertSame(self::SERVE_EXIT_CODE, self::exitCode($children->start(CoreRole::Activity)));
    }

    #[RunInSeparateProcess]
    public function testFailedForkedChildExitsWithOne(): void
    {
        $children = new ChildProcesses(static fn(): never => throw new \RuntimeException('serve broke'), new NullLogger(), true, []);

        self::assertSame(1, self::exitCode($children->start(CoreRole::Workflow)));
    }

    #[RunInSeparateProcess]
    public function testProcessLimitIsReported(): void
    {
        $logger = new LoggerSpy();
        \posix_setrlimit(\POSIX_RLIMIT_NPROC, 1, 1);
        $errors = [];
        foreach ([true, false] as $fork) {
            try {
                @(new ChildProcesses(static fn(): int => 0, $logger, $fork, [self::CHILD_SCRIPT]))->start(CoreRole::Activity);
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        self::assertSame(['Unable to fork a activity worker process', 'Unable to start a activity worker process'], $errors);
        self::assertStringStartsWith('Unable to read the default ini of the PHP binary', $logger->records[0]['message']);
    }

    public function testSpawnedChildGetsItsRoleAndTheIni(): void
    {
        $output = (string) \tempnam(\sys_get_temp_dir(), 'core-child');
        $scanDir = \getenv('PHP_INI_SCAN_DIR');
        $ini = ['memory_limit' => '1G', 'display_startup_errors' => '0', 'error_log' => '/dev/null'];
        $previous = \array_map(\ini_get(...), \array_combine(\array_keys($ini), \array_keys($ini)));
        \array_walk($ini, static fn(string $value, string $name): string|false => \ini_set($name, $value));
        \putenv('PHP_INI_SCAN_DIR=' . \sys_get_temp_dir() . '/no-ini-scan-dir');
        try {
            $children = new ChildProcesses(static fn(): int => 0, new NullLogger(), false, [self::CHILD_SCRIPT, $output]);
            $activity = $children->start(CoreRole::Activity);
            $activityExitCode = self::exitCode($activity);
            $children->release($activity);
            $children->release($activity);
            $workflowExitCode = self::exitCode($children->start(CoreRole::Workflow));
        } finally {
            \array_walk($previous, static fn(string|false $value, string $name): string|false => \ini_set($name, (string) $value));
            \putenv($scanDir === false ? 'PHP_INI_SCAN_DIR' : "PHP_INI_SCAN_DIR=$scanDir");
        }

        self::assertSame([self::ACTIVITY_EXIT_CODE, self::WORKFLOW_EXIT_CODE], [$activityExitCode, $workflowExitCode]);
        self::assertSame('1G', \file_get_contents($output));
        \unlink($output);
    }

    private static function exitCode(int $pid): int
    {
        \pcntl_waitpid($pid, $status);

        return \pcntl_wexitstatus($status);
    }
}
