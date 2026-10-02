<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing\Replay;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Testing\Replay\HistoryJsonCodec;

#[CoversClass(HistoryJsonCodec::class)]
final class HistoryJsonCodecTestCase extends TestCase
{
    public static function provideEventTypeNames(): iterable
    {
        yield 'full name' => ['EVENT_TYPE_WORKFLOW_EXECUTION_STARTED'];
        yield 'short name' => ['WorkflowExecutionStarted'];
        yield 'short name with prefix' => ['EventTypeWorkflowExecutionStarted'];
    }

    #[DataProvider('provideEventTypeNames')]
    public function testEventTypeNamesDecode(string $name): void
    {
        $history = (new HistoryJsonCodec())->decode(\json_encode(['events' => [['eventId' => '1', 'eventType' => $name]]]));

        $this->assertSame(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, $history->getEvents()[0]->getEventType());
    }

    public function testSnakeCaseKeysDecode(): void
    {
        $history = (new HistoryJsonCodec())->decode(\json_encode(['events' => [['event_id' => '1', 'event_type' => 'WorkflowExecutionStarted']]]));

        $this->assertSame(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, $history->getEvents()[0]->getEventType());
    }

    public function testLastEventIdCutsTheHistory(): void
    {
        $json = \json_encode(['events' => [
            ['eventId' => '1', 'eventType' => 'WorkflowExecutionStarted'],
            ['eventId' => '2', 'eventType' => 'WorkflowTaskScheduled'],
            ['eventId' => '3', 'eventType' => 'WorkflowTaskStarted'],
        ]]);
        $codec = new HistoryJsonCodec();

        $this->assertCount(2, $codec->decode($json, 2)->getEvents());
        $this->assertCount(3, $codec->decode($json)->getEvents());
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $codec = new HistoryJsonCodec();
        $history = $codec->decode(\json_encode(['events' => [['eventId' => '1', 'eventType' => 'WorkflowExecutionStarted']]]));

        $this->assertSame($history->serializeToString(), $codec->decode($codec->encode($history))->serializeToString());
    }
}
