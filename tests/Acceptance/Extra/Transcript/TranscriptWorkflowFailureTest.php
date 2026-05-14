<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Transcript\TranscriptWorkflowFailure;

use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Logger\TranscriptLine;
use Temporal\Tests\Acceptance\App\Logger\TranscriptSection;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class TranscriptWorkflowFailureTest extends TestCase
{
    public function testWorkflowFailureCapturedWithHistory(
        #[Stub('Extra_Transcript_TranscriptWorkflowFailure_run')]
        WorkflowStubInterface $stub,
    ): void {
        $thrown = null;
        try {
            $stub->getResult('string');
        } catch (\Throwable $exception) {
            $thrown = $exception;
        }
        self::assertInstanceOf(WorkflowFailedException::class, $thrown);

        $lines = $this->readCurrentTestTranscript();

        // The workflow execute interceptor wraps the synchronous setup of the workflow scope;
        // the generator body's throw is delivered asynchronously, so it surfaces via WIRE_OUTBOUND
        // (failure response to RoadRunner) rather than via the interceptor's catch.
        $executeMarkers = \array_filter(
            $lines,
            static fn(TranscriptLine $line): bool => $line->section === TranscriptSection::META
                && ($line->attributes['event'] ?? null) === 'workflow_execute_start',
        );
        $outbound = \array_filter($lines, static fn(TranscriptLine $line): bool => $line->section === TranscriptSection::WIRE_OUTBOUND);
        self::assertNotEmpty($executeMarkers, 'Expected workflow_execute_start META marker');
        self::assertNotEmpty($outbound, 'Expected at least one WIRE_OUTBOUND frame');
    }
}

#[WorkflowInterface]
class FailingWorkflow
{
    #[WorkflowMethod(name: 'Extra_Transcript_TranscriptWorkflowFailure_run')]
    public function run(): \Generator
    {
        yield;
        throw new ApplicationFailure('workflow-boom', 'TestWorkflowFailure', false);
    }
}
