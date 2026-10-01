<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use Coresdk\Activity_task\ActivityTask;
use Coresdk\Activity_task\Start;
use Coresdk\ActivityTaskCompletion;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\Worker\Core\ActivityTasks;
use Temporal\Internal\Bridge\Bridge;

#[RequiresPhpExtension('ffi')]
final class ActivityTasksTestCase extends TestCase
{
    public function testActivityWithoutResultFailsOnlyItsTask(): void
    {
        $completion = $this->handle(static fn(): array => [], 'Act');

        self::assertSame('token', $completion->getTaskToken());
        self::assertSame('Activity produced no result', $completion->getResult()->getFailed()->getFailure()->getMessage());
    }

    public function testDispatchErrorFailsOnlyItsTask(): void
    {
        $completion = $this->handle(static fn(): never => throw new \RuntimeException('dispatch broke'), 'Act');

        self::assertSame('token', $completion->getTaskToken());
        self::assertSame('dispatch broke', $completion->getResult()->getFailed()->getFailure()->getMessage());
    }

    public function testSideEffectWithoutInputCompletesWithoutResult(): void
    {
        $completion = $this->handle(static fn(): array => [], ActivityTasks::SIDE_EFFECT);

        self::assertTrue($completion->getResult()->hasCompleted());
        self::assertNull($completion->getResult()->getCompleted()->getResult());
    }

    private function handle(\Closure $dispatch, string $type): ActivityTaskCompletion
    {
        $tasks = new ActivityTasks(DataConverter::createDefault());
        $bridge = (new \ReflectionClass(Bridge::class))->newInstanceWithoutConstructor();
        $tasks->bind($bridge, $dispatch);
        $task = new ActivityTask(['task_token' => 'token', 'start' => new Start([
            'activity_id' => '1',
            'activity_type' => $type,
        ])]);

        $completion = new ActivityTaskCompletion();
        $completion->mergeFromString((string) $tasks->handle(\FFI::cdef()->new('int'), 'queue', $task->serializeToString()));

        return $completion;
    }
}
