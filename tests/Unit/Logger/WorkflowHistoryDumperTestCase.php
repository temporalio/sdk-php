<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Logger;

use Google\Protobuf\Timestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Enums\V1\WorkflowTaskFailedCause;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\StartChildWorkflowExecutionFailedEventAttributes;
use Temporal\Api\History\V1\WorkflowExecutionFailedEventAttributes;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Client\Common\Paginator;
use Temporal\Client\Workflow\WorkflowExecutionHistory;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Testing\Transcript\MalformedTranscriptException;
use Temporal\Testing\Transcript\TranscriptLine;
use Temporal\Testing\Transcript\TranscriptReader;
use Temporal\Testing\Transcript\TranscriptSection;
use Temporal\Testing\Transcript\TranscriptWriter;
use Temporal\Testing\Transcript\WorkflowHistoryDumper;
use Temporal\Workflow\WorkflowExecution;

#[CoversClass(WorkflowHistoryDumper::class)]
#[UsesClass(TranscriptWriter::class)]
#[UsesClass(TranscriptReader::class)]
#[UsesClass(TranscriptLine::class)]
#[UsesClass(TranscriptSection::class)]
#[UsesClass(MalformedTranscriptException::class)]
#[UsesClass(WorkflowExecution::class)]
#[UsesClass(WorkflowExecutionHistory::class)]
#[UsesClass(Paginator::class)]
#[UsesClass(HistoryEvent::class)]
#[UsesClass(History::class)]
#[UsesClass(GetWorkflowExecutionHistoryResponse::class)]
#[UsesClass(EventType::class)]
#[UsesClass(Timestamp::class)]
final class WorkflowHistoryDumperTestCase extends TestCase
{
    use TranscriptTestSupport;


    public function testWritesHistorySkippedMetaWhenArgsAreEmpty(): void
    {
        $writer = $this->newWriter('empty.log');
        $client = $this->createMock(WorkflowClientInterface::class);
        $client->expects(self::never())->method('getWorkflowHistory');

        (new WorkflowHistoryDumper())->dump($writer, $client, []);
        $writer->flush();

        $meta = $this->readMeta();
        self::assertCount(1, $meta);
        self::assertSame('history_skipped', $meta[0]->attributes['event']);
        self::assertSame('no_executions_inspected', $meta[0]->attributes['reason']);
    }

    public function testWritesHistorySkippedWhenNoStubsPresent(): void
    {
        $writer = $this->newWriter('nonstub.log');
        $client = $this->createMock(WorkflowClientInterface::class);
        $client->expects(self::never())->method('getWorkflowHistory');

        (new WorkflowHistoryDumper())->dump($writer, $client, ['just-a-string', 42, new \stdClass()]);
        $writer->flush();

        $meta = $this->readMeta();
        self::assertSame('history_skipped', $meta[0]->attributes['event']);
    }

    public function testWritesHistoryEventsAndDumpedMetaForSingleExecution(): void
    {
        $writer = $this->newWriter('single.log');
        $execution = new WorkflowExecution('wf-1', 'run-1');
        $stub = $this->createMock(WorkflowStubInterface::class);
        $stub->method('getExecution')->willReturn($execution);

        $events = [
            $this->newEvent(1, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, 1700000000, 123),
            $this->newEvent(2, EventType::EVENT_TYPE_WORKFLOW_TASK_SCHEDULED, 1700000001, 456),
        ];
        $client = $this->createMock(WorkflowClientInterface::class);
        $client->method('getWorkflowHistory')->willReturn($this->makeHistory($events));

        (new WorkflowHistoryDumper())->dump($writer, $client, [$stub]);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $history = $reader->findBySection(TranscriptSection::HISTORY);
        self::assertCount(2, $history);
        self::assertSame(1, $history[0]->attributes['event_id']);
        self::assertSame('EVENT_TYPE_WORKFLOW_EXECUTION_STARTED', $history[0]->attributes['event_type']);
        self::assertSame('wf-1', $history[0]->attributes['workflow_id']);
        self::assertSame('run-1', $history[0]->attributes['run_id']);
        self::assertSame('1700000000.123', $history[0]->attributes['event_time']);
        self::assertSame(2, $history[1]->attributes['event_id']);

        $dumpedMetas = \array_values(\array_filter(
            $reader->findBySection(TranscriptSection::META),
            static fn(TranscriptLine $line): bool => ($line->attributes['event'] ?? null) === 'history_dumped',
        ));
        self::assertCount(1, $dumpedMetas);
        self::assertSame('wf-1', $dumpedMetas[0]->attributes['workflow_id']);
        self::assertSame(2, $dumpedMetas[0]->attributes['event_count']);
    }

    public function testDeduplicatesExecutionsWithSameIdAndRunId(): void
    {
        $writer = $this->newWriter('dedup.log');
        $execution = new WorkflowExecution('wf-dup', 'run-1');
        $stubA = $this->createMock(WorkflowStubInterface::class);
        $stubA->method('getExecution')->willReturn($execution);
        $stubB = $this->createMock(WorkflowStubInterface::class);
        $stubB->method('getExecution')->willReturn(new WorkflowExecution('wf-dup', 'run-1'));

        $client = $this->createMock(WorkflowClientInterface::class);
        $client->expects(self::once())
            ->method('getWorkflowHistory')
            ->willReturn($this->makeHistory([
                $this->newEvent(1, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
            ]));

        (new WorkflowHistoryDumper())->dump($writer, $client, [$stubA, $stubB]);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $dumpedMetas = \array_values(\array_filter(
            $reader->findBySection(TranscriptSection::META),
            static fn(TranscriptLine $line): bool => ($line->attributes['event'] ?? null) === 'history_dumped',
        ));
        self::assertCount(1, $dumpedMetas, 'Same execution id+runId should be dumped once');
    }

    public function testDumpsBothExecutionsWhenSameIdButDifferentRunId(): void
    {
        $writer = $this->newWriter('two-runs.log');
        $stubA = $this->createMock(WorkflowStubInterface::class);
        $stubA->method('getExecution')->willReturn(new WorkflowExecution('wf-retry', 'run-1'));
        $stubB = $this->createMock(WorkflowStubInterface::class);
        $stubB->method('getExecution')->willReturn(new WorkflowExecution('wf-retry', 'run-2'));

        $client = $this->createMock(WorkflowClientInterface::class);
        $client->expects(self::exactly(2))
            ->method('getWorkflowHistory')
            ->willReturnOnConsecutiveCalls(
                $this->makeHistory([$this->newEvent(1, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED)]),
                $this->makeHistory([$this->newEvent(1, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED)]),
            );

        (new WorkflowHistoryDumper())->dump($writer, $client, [$stubA, $stubB]);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $dumpedMetas = \array_values(\array_filter(
            $reader->findBySection(TranscriptSection::META),
            static fn(TranscriptLine $line): bool => ($line->attributes['event'] ?? null) === 'history_dumped',
        ));
        self::assertCount(2, $dumpedMetas);
        $runIds = \array_map(static fn(TranscriptLine $line): mixed => $line->attributes['run_id'], $dumpedMetas);
        self::assertEqualsCanonicalizing(['run-1', 'run-2'], $runIds);
    }

    public function testWritesHistoryErrorWhenClientThrows(): void
    {
        $writer = $this->newWriter('err.log');
        $stub = $this->createMock(WorkflowStubInterface::class);
        $stub->method('getExecution')->willReturn(new WorkflowExecution('wf-err', 'run-x'));

        $client = $this->createMock(WorkflowClientInterface::class);
        $client->method('getWorkflowHistory')->willThrowException(new \RuntimeException('temporal-unreachable'));

        (new WorkflowHistoryDumper())->dump($writer, $client, [$stub]);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $errors = $reader->findBySection(TranscriptSection::HISTORY_ERROR);
        self::assertCount(1, $errors);
        self::assertSame('wf-err', $errors[0]->attributes['workflow_id']);
        self::assertSame(\RuntimeException::class, $errors[0]->attributes['class']);
        self::assertSame('temporal-unreachable', $errors[0]->payload['message']);

        $dumped = \array_filter(
            $reader->findBySection(TranscriptSection::META),
            static fn(TranscriptLine $line): bool => ($line->attributes['event'] ?? null) === 'history_dumped',
        );
        self::assertEmpty($dumped);
    }

    public function testEnrichesHistoryEntriesWithDeltaMsAndFailureSummary(): void
    {
        $writer = $this->newWriter('enrich.log');
        $stub = $this->createMock(WorkflowStubInterface::class);
        $stub->method('getExecution')->willReturn(new WorkflowExecution('wf-1', 'run-1'));

        $start = $this->newEvent(1, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, 1700000000, 0);
        $failed = $this->newEvent(2, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_FAILED, 1700000000, 250_000_000);
        $failed->setWorkflowExecutionFailedEventAttributes(
            (new WorkflowExecutionFailedEventAttributes())->setFailure(
                (new Failure())
                    ->setMessage('Should not be called')
                    ->setSource('PHP_SDK')
                    ->setApplicationFailureInfo(
                        (new ApplicationFailureInfo())->setType('Exception'),
                    ),
            ),
        );

        $client = $this->createMock(WorkflowClientInterface::class);
        $client->method('getWorkflowHistory')->willReturn($this->makeHistory([$start, $failed]));

        (new WorkflowHistoryDumper())->dump($writer, $client, [$stub]);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $history = $reader->findBySection(TranscriptSection::HISTORY);
        self::assertCount(2, $history);
        self::assertSame(0, $history[0]->attributes['delta_ms']);
        self::assertSame(250, $history[1]->attributes['delta_ms']);
        self::assertSame('application_failure_info', $history[1]->attributes['failure_kind']);
        self::assertSame('Should not be called', $history[1]->attributes['failure_message']);
        self::assertArrayNotHasKey('failure_kind', $history[0]->attributes);
        self::assertArrayNotHasKey('cause', $history[0]->attributes);
    }

    public function testRecordsCauseAttributeForChildWorkflowStartFailure(): void
    {
        $writer = $this->newWriter('cause.log');
        $stub = $this->createMock(WorkflowStubInterface::class);
        $stub->method('getExecution')->willReturn(new WorkflowExecution('wf-c', 'run-c'));

        $event = $this->newEvent(7, EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_FAILED, 1700000000, 0);
        $event->setStartChildWorkflowExecutionFailedEventAttributes(
            (new StartChildWorkflowExecutionFailedEventAttributes())
                ->setCause(WorkflowTaskFailedCause::WORKFLOW_TASK_FAILED_CAUSE_WORKFLOW_WORKER_UNHANDLED_FAILURE),
        );

        $client = $this->createMock(WorkflowClientInterface::class);
        $client->method('getWorkflowHistory')->willReturn($this->makeHistory([$event]));

        (new WorkflowHistoryDumper())->dump($writer, $client, [$stub]);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $history = $reader->findBySection(TranscriptSection::HISTORY);
        self::assertCount(1, $history);
        self::assertArrayHasKey('cause', $history[0]->attributes);
        self::assertSame(
            (string) WorkflowTaskFailedCause::WORKFLOW_TASK_FAILED_CAUSE_WORKFLOW_WORKER_UNHANDLED_FAILURE,
            (string) $history[0]->attributes['cause'],
        );
        self::assertArrayNotHasKey('failure_kind', $history[0]->attributes);
    }

    public function testRecordsSerializeErrorAttributeWhenEventSerializationFails(): void
    {
        $writer = $this->newWriter('serr.log');
        $stub = $this->createMock(WorkflowStubInterface::class);
        $stub->method('getExecution')->willReturn(new WorkflowExecution('wf-serr', 'run-1'));

        $event = $this->createMock(HistoryEvent::class);
        $event->method('getEventId')->willReturn(7);
        $event->method('getEventType')->willReturn(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED);
        $event->method('getEventTime')->willReturn(null);
        $event->method('serializeToJsonString')->willThrowException(new \RuntimeException('bad-utf8'));

        $client = $this->createMock(WorkflowClientInterface::class);
        $client->method('getWorkflowHistory')->willReturn($this->makeHistory([$event]));

        (new WorkflowHistoryDumper())->dump($writer, $client, [$stub]);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $history = $reader->findBySection(TranscriptSection::HISTORY);
        self::assertCount(1, $history);
        self::assertSame(7, $history[0]->attributes['event_id']);
        self::assertSame('bad-utf8', $history[0]->attributes['serialize_error']);
        self::assertSame('{}', $history[0]->payload['attrs']);
    }

    private function newWriter(string $name): TranscriptWriter
    {
        return new TranscriptWriter($this->directory . '/' . $name);
    }

    /**
     * @return list<TranscriptLine>
     */
    private function readMeta(): array
    {
        $reader = new TranscriptReader($this->directory);
        return \array_values(\array_filter(
            $reader->findBySection(TranscriptSection::META),
            static fn(TranscriptLine $line): bool => ($line->attributes['event'] ?? null) !== 'writer_initialized',
        ));
    }

    private function newEvent(int $id, int $type, ?int $seconds = null, int $nanos = 0): HistoryEvent
    {
        $event = new HistoryEvent();
        $event->setEventId($id);
        $event->setEventType($type);
        if ($seconds !== null) {
            $timestamp = new Timestamp();
            $timestamp->setSeconds($seconds);
            $timestamp->setNanos($nanos);
            $event->setEventTime($timestamp);
        }
        return $event;
    }

    /**
     * @param list<HistoryEvent> $events
     */
    private function makeHistory(array $events): WorkflowExecutionHistory
    {
        $history = new History();
        $history->setEvents($events);
        $response = new GetWorkflowExecutionHistoryResponse();
        $response->setHistory($history);
        $generator = (static function () use ($response): \Generator {
            yield [$response];
        })();
        return new WorkflowExecutionHistory(Paginator::createFromGenerator($generator, null));
    }
}
