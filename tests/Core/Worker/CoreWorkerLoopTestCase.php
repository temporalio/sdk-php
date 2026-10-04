<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Worker;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Tests\Unit\Client\Stub\LoggerSpy;
use Temporal\Worker\Core\ActivityTasks;
use Temporal\Worker\Core\CoreWorkerHandle;
use Temporal\Worker\Core\CoreWorkerLoop;
use Temporal\Worker\Core\WorkflowActivations;
use Temporal\Tests\Core\Replayers;

final class CoreWorkerLoopTestCase extends TestCase
{
    private const FIRST_TASK_EVENT_ID = 3;
    private const FOREIGN_TAG = 99;
    private const SHUT_DOWN = 'sdk-core worker shut down unexpectedly, stopping the process';

    private LoggerSpy $logger;
    private Bridge $bridge;

    protected function setUp(): void
    {
        $this->logger = new LoggerSpy();
        $this->bridge = Bridge::shared();
        $this->bridge->useLogger(new NullLogger());
    }

    public function testEndedReplayCrashesTheProcess(): void
    {
        $code = $this->serve(self::handle(static fn(): array => []));

        self::assertSame(1, $code);
        self::assertSame([self::SHUT_DOWN], $this->errors());
    }

    public function testNoWorkersMeansNothingToServe(): void
    {
        self::assertSame(0, $this->loop()->serve(static fn(): array => []));
    }

    public function testStopSignalShutsDownCleanly(): void
    {
        $code = $this->serve(self::handle(static function (): array {
            \posix_kill(\getmypid(), \SIGTERM);
            return [];
        }));

        self::assertSame(0, $code);
        self::assertSame([], $this->errors());
    }

    public function testStopBeforePollingShutsDownCleanly(): void
    {
        $code = $this->loop()->serve(function (): array {
            \posix_kill(\getmypid(), \SIGTERM);
            return [self::handle(static fn(): array => [])];
        });

        self::assertSame(0, $code);
        self::assertSame([], $this->errors());
    }

    public function testRejectedCompletionIsLogged(): void
    {
        $core = null;
        $handle = self::handle(function () use (&$core): array {
            $this->bridge->completeWorkflowActivation($core, 0, 'x');
            return [];
        });
        $core = $handle->core;

        $this->serve($handle);

        self::assertStringStartsWith('sdk-core completion failed: Decode failure', $this->errors()[0]);
    }

    public function testGoneSupervisorStopsTheProcess(): void
    {
        $code = $this->loop(supervisorPid: \posix_getppid() + 1)->serve(static fn(): array => [self::handle(static fn(): array => [])]);

        self::assertSame(0, $code);
        self::assertSame(['The supervisor process is gone, stopping'], $this->errors());
    }

    public function testConcurrentLoopUsesTheEventPipe(): void
    {
        $code = $this->loop(concurrent: true)->serve(static fn(): array => [self::handle(static fn(): array => [])]);

        self::assertSame(1, $code);
        self::assertSame([self::SHUT_DOWN], $this->errors());
    }

    public function testFailedActivityPollCrashesTheProcess(): void
    {
        $code = $this->serve(self::handle(static fn(): array => [], workflows: false, activities: true));

        self::assertSame(1, $code);
        self::assertSame([self::SHUT_DOWN], $this->errors());
    }

    public function testWorkerWithAnOutstandingActivationIsReported(): void
    {
        $handle = self::handle(static fn(): array => [], workflows: false);
        $this->bridge->pollWorkflowActivation($handle->core, self::FOREIGN_TAG);
        while ($this->bridge->nextEvents(Bridge::POLL_TIMEOUT_MS) === []);
        $this->bridge->completeActivityTask($handle->core, 0, 'x');

        $code = $this->serve($handle);

        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/^sdk-core worker for task queue "default" did not finalize: Decode failure.*; The sdk-core worker did not finalize in time$/', $this->errors()[0]);
    }

    private function serve(CoreWorkerHandle $handle): int
    {
        return $this->loop()->serve(static fn(): array => [$handle]);
    }

    private function loop(bool $concurrent = false, ?int $supervisorPid = null): CoreWorkerLoop
    {
        $activityTasks = new ActivityTasks();
        $activityTasks->bind($this->bridge, static fn(): array => [], DataConverter::createDefault());

        return new CoreWorkerLoop($this->bridge, $activityTasks, $this->logger, $concurrent, $supervisorPid);
    }

    /**
     * @return list<string>
     */
    private function errors(): array
    {
        return \array_column($this->logger->records, 'message');
    }

    private static function handle(\Closure $dispatch, bool $workflows = true, bool $activities = false): CoreWorkerHandle
    {
        return new CoreWorkerHandle(
            Replayers::create(Bridge::shared(), self::FIRST_TASK_EVENT_ID),
            'default',
            new WorkflowActivations(DataConverter::createDefault(), $dispatch, 'default', 'default', [], false),
            $workflows,
            $activities,
        );
    }
}
