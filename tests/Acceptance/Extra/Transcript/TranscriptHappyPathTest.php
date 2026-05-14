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

class TranscriptHappyPathTest extends TestCase
{
    public function testHappyPathRoundTripIsCaptured(
        #[Stub('Extra_Transcript_TranscriptHappyPath_run')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('string');
        self::assertSame('hello-from-activity', $result);

        $lines = $this->readCurrentTestTranscript();
        self::assertNotEmpty($lines, 'No transcript lines were captured for this test');

        $wireInbound = \array_filter($lines, static fn(TranscriptLine $line): bool => $line->section === TranscriptSection::WIRE_INBOUND);
        $wireOutbound = \array_filter($lines, static fn(TranscriptLine $line): bool => $line->section === TranscriptSection::WIRE_OUTBOUND);

        self::assertGreaterThan(0, \count($wireInbound), 'Expected at least one WIRE_INBOUND frame from the worker');
        self::assertGreaterThan(0, \count($wireOutbound), 'Expected at least one WIRE_OUTBOUND frame from the worker');
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
