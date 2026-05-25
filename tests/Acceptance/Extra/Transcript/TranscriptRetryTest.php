<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Transcript\TranscriptRetry;

use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Logger\TranscriptLine;
use Temporal\Tests\Acceptance\App\Logger\TranscriptSection;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

final class TranscriptRetryTest extends TestCase
{
    public function testRetriesAreRecordedPerAttempt(
        #[Stub('Extra_Transcript_TranscriptRetry_run')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('string');
        self::assertSame('eventually-ok', $result);

        $lines = $this->readCurrentTestTranscript();

        $throwsByAttempt = [];
        $messagesByAttempt = [];
        foreach ($lines as $line) {
            if ($line->section !== TranscriptSection::EXCEPTION) {
                continue;
            }
            if (($line->attributes['phase'] ?? null) !== 'activity_throw') {
                continue;
            }
            $attempt = (int) ($line->attributes['attempt'] ?? 0);
            $throwsByAttempt[$attempt] = ($throwsByAttempt[$attempt] ?? 0) + 1;
            $messagesByAttempt[$attempt] = (string) ($line->payload['message'] ?? '');
        }
        self::assertSame(1, $throwsByAttempt[1] ?? 0, 'Exactly one activity_throw expected for attempt=1');
        self::assertSame(1, $throwsByAttempt[2] ?? 0, 'Exactly one activity_throw expected for attempt=2');
        self::assertArrayNotHasKey(3, $throwsByAttempt, 'Attempt 3 should succeed without throw');
        self::assertStringContainsString('boom-attempt-1', $messagesByAttempt[1] ?? '');
        self::assertStringContainsString('boom-attempt-2', $messagesByAttempt[2] ?? '');

        $activityStartsByAttempt = [];
        foreach ($lines as $line) {
            if ($line->section !== TranscriptSection::META) {
                continue;
            }
            if (($line->attributes['event'] ?? null) !== 'activity_start') {
                continue;
            }
            $attempt = (int) ($line->attributes['attempt'] ?? 0);
            $activityStartsByAttempt[$attempt] = ($activityStartsByAttempt[$attempt] ?? 0) + 1;
        }
        self::assertSame(1, $activityStartsByAttempt[1] ?? 0);
        self::assertSame(1, $activityStartsByAttempt[2] ?? 0);
        self::assertSame(1, $activityStartsByAttempt[3] ?? 0, 'Attempt 3 must record an activity_start');
    }
}

#[WorkflowInterface]
class RetryWorkflow
{
    #[WorkflowMethod(name: 'Extra_Transcript_TranscriptRetry_run')]
    public function run(): \Generator
    {
        $activity = Workflow::newActivityStub(
            RetryActivity::class,
            Activity\ActivityOptions::new()
                ->withScheduleToCloseTimeout(30)
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(3)->withInitialInterval(1)),
        );
        return yield $activity->flaky();
    }
}

#[ActivityInterface(prefix: 'Extra_Transcript_TranscriptRetry.')]
class RetryActivity
{
    #[ActivityMethod]
    public function flaky(): string
    {
        $attempt = Activity::getInfo()->attempt;
        if ($attempt < 3) {
            throw new ApplicationFailure(
                "boom-attempt-{$attempt}",
                'TestFailure',
                false,
            );
        }
        return 'eventually-ok';
    }
}
