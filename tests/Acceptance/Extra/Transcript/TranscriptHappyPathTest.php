<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Transcript\TranscriptHappyPath;

use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Logger\TranscriptLine;
use Temporal\Tests\Acceptance\App\Logger\TranscriptSection;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

final class TranscriptHappyPathTest extends TestCase
{
    public function testHappyPathRoundTripIsCaptured(
        #[Stub('Extra_Transcript_TranscriptHappyPath_run')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('string');
        self::assertSame('hello-from-activity', $result);

        $lines = $this->readCurrentTestTranscript();
        self::assertNotEmpty($lines, 'No transcript lines were captured for this test');

        $workflowStart = $this->findMeta($lines, 'workflow_execute_start');
        self::assertCount(1, $workflowStart, 'Expected exactly one workflow_execute_start META');
        self::assertSame('Extra_Transcript_TranscriptHappyPath_run', $workflowStart[0]->attributes['workflow_type']);
        self::assertSame($stub->getExecution()->getID(), $workflowStart[0]->attributes['workflow_id']);

        $workflowCompleted = $this->findMeta($lines, 'workflow_execute_completed');
        self::assertCount(1, $workflowCompleted, 'Expected exactly one workflow_execute_completed META');

        $activityStart = $this->findMeta($lines, 'activity_start');
        self::assertCount(1, $activityStart, 'Expected exactly one activity_start META');
        self::assertSame('Extra_Transcript_TranscriptHappyPath.greet', $activityStart[0]->attributes['name']);
        self::assertSame(1, $activityStart[0]->attributes['attempt']);

        $activityCompleted = $this->findMeta($lines, 'activity_completed');
        self::assertCount(1, $activityCompleted, 'Expected exactly one activity_completed META');
    }

    /**
     * @param list<TranscriptLine> $lines
     * @return list<TranscriptLine>
     */
    private function findMeta(array $lines, string $event): array
    {
        return \array_values(\array_filter(
            $lines,
            static fn(TranscriptLine $line): bool => $line->section === TranscriptSection::META
                && ($line->attributes['event'] ?? null) === $event,
        ));
    }
}

#[WorkflowInterface]
class HappyPathWorkflow
{
    #[WorkflowMethod(name: 'Extra_Transcript_TranscriptHappyPath_run')]
    public function run(): \Generator
    {
        $activity = Workflow::newActivityStub(
            HappyPathActivity::class,
            Activity\ActivityOptions::new()->withScheduleToCloseTimeout(10),
        );
        return yield $activity->greet();
    }
}

#[ActivityInterface(prefix: 'Extra_Transcript_TranscriptHappyPath.')]
class HappyPathActivity
{
    #[ActivityMethod]
    public function greet(): string
    {
        return 'hello-from-activity';
    }
}
