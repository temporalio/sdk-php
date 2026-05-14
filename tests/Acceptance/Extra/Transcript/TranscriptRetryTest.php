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

class TranscriptRetryTest extends TestCase
{
    public function testRetriesAreRecordedPerAttempt(
        #[Stub('Extra_Transcript_TranscriptRetry_run')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('string');
        self::assertSame('eventually-ok', $result);

        $lines = $this->readCurrentTestTranscript();

        $throwsByAttempt = [];
        foreach ($lines as $line) {
            if ($line->section !== TranscriptSection::EXCEPTION) {
                continue;
            }
            if (($line->attributes['phase'] ?? null) !== 'activity_throw') {
                continue;
            }
            $attempt = (int) ($line->attributes['attempt'] ?? 0);
            $throwsByAttempt[$attempt] = ($throwsByAttempt[$attempt] ?? 0) + 1;
        }
        self::assertArrayHasKey(1, $throwsByAttempt, 'Expected exception line for attempt=1');
        self::assertArrayHasKey(2, $throwsByAttempt, 'Expected exception line for attempt=2');
        self::assertArrayNotHasKey(3, $throwsByAttempt, 'Attempt 3 should succeed without throw');

        $wireOutbound = \array_filter($lines, static fn(TranscriptLine $line): bool => $line->section === TranscriptSection::WIRE_OUTBOUND);
        self::assertGreaterThanOrEqual(3, \count($wireOutbound), 'Expected at least 3 worker outbound frames covering retries');
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
