<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Nexus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Link\WorkflowEvent;
use Temporal\Api\Common\V1\Link\WorkflowEvent\EventReference;
use Temporal\Api\Common\V1\Link\WorkflowEvent\RequestIdReference;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Link as NexusLink;

#[CoversClass(NexusLinkConverter::class)]
final class NexusLinkConverterTestCase extends TestCase
{
    private const TYPE = NexusLinkConverter::TYPE_WORKFLOW_EVENT;

    public function testEventReferenceHappyPath(): void
    {
        $uri = 'temporal:///namespaces/default/workflows/wf-123/run-456/history'
            . '?referenceType=EventReference&eventID=42&eventType=EVENT_TYPE_NEXUS_OPERATION_STARTED';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        self::assertCount(1, $result);
        $event = $result[0]->getWorkflowEvent();
        self::assertNotNull($event);
        self::assertSame('default', $event->getNamespace());
        self::assertSame('wf-123', $event->getWorkflowId());
        self::assertSame('run-456', $event->getRunId());

        $eventRef = $event->getEventRef();
        self::assertNotNull($eventRef);
        self::assertSame(42, (int) $eventRef->getEventId());
        self::assertSame(EventType::EVENT_TYPE_NEXUS_OPERATION_STARTED, $eventRef->getEventType());
    }

    public function testRequestIdReferenceHappyPath(): void
    {
        $uri = 'temporal:///namespaces/default/workflows/wf-1/run-1/history'
            . '?referenceType=RequestIdReference&requestID=req-abc&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        self::assertCount(1, $result);
        $event = $result[0]->getWorkflowEvent();
        self::assertNotNull($event);
        $ref = $event->getRequestIdRef();
        self::assertNotNull($ref);
        self::assertSame('req-abc', $ref->getRequestId());
        self::assertSame(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, $ref->getEventType());
    }

    public function testEventReferenceWithoutEventId(): void
    {
        $uri = 'temporal:///namespaces/default/workflows/wf/run/history'
            . '?referenceType=EventReference&eventType=EVENT_TYPE_NEXUS_OPERATION_STARTED';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        self::assertCount(1, $result);
        $eventRef = $result[0]->getWorkflowEvent()->getEventRef();
        self::assertNotNull($eventRef);
        self::assertSame(0, (int) $eventRef->getEventId());
    }

    public function testSkipsNonWorkflowEventLinks(): void
    {
        $result = NexusLinkConverter::toProtoLinks([
            new NexusLink('https://custom-tracker/123', 'custom.tracking.event'),
        ]);
        self::assertSame([], $result);
    }

    public function testMixedListKeepsWorkflowEventOnly(): void
    {
        $good1 = 'temporal:///namespaces/n/workflows/w/r/history?referenceType=EventReference&eventID=1&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $good2 = 'temporal:///namespaces/n/workflows/w2/r2/history?referenceType=EventReference&eventID=2&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $result = NexusLinkConverter::toProtoLinks([
            new NexusLink($good1, self::TYPE),
            new NexusLink('https://custom/abc', 'custom.tracking'),
            new NexusLink($good2, self::TYPE),
        ]);

        self::assertCount(2, $result);
    }

    public function testPathDecodesEscapedSegments(): void
    {
        $uri = 'temporal:///namespaces/ns%20with%20space/workflows/wf%2Fslash/run-1/history'
            . '?referenceType=EventReference&eventID=1&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        $event = $result[0]->getWorkflowEvent();
        self::assertSame('ns with space', $event->getNamespace());
        self::assertSame('wf/slash', $event->getWorkflowId());
    }

    public function testRejectsBadScheme(): void
    {
        $uri = 'https:///namespaces/n/workflows/w/r/history?referenceType=EventReference&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/scheme/');
        NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);
    }

    public function testRejectsMalformedPath(): void
    {
        $uri = 'temporal:///workflows/w/r/history?referenceType=EventReference&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/path/');
        NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);
    }

    public function testRejectsUnknownEventType(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history?referenceType=EventReference&eventType=EVENT_TYPE_DOES_NOT_EXIST';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/EventType/');
        NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);
    }

    public function testRejectsUnknownReferenceType(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history?referenceType=NotARealType&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/referenceType/');
        NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);
    }

    public function testRejectsNonNumericEventId(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history?referenceType=EventReference&eventID=abc&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $this->expectException(InvalidArgumentException::class);
        NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);
    }

    public function testAcceptsMissingRequestIdAsEmpty(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history?referenceType=RequestIdReference&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        self::assertCount(1, $result);
        $ref = $result[0]->getWorkflowEvent()->getRequestIdRef();
        self::assertNotNull($ref);
        self::assertSame('', $ref->getRequestId());
    }

    public function testParsesPascalCaseEventReference(): void
    {
        $uri = 'temporal:///namespaces/default/workflows/wf/run/history'
            . '?referenceType=EventReference&eventID=7&eventType=WorkflowExecutionStarted';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        self::assertCount(1, $result);
        $eventRef = $result[0]->getWorkflowEvent()->getEventRef();
        self::assertNotNull($eventRef);
        self::assertSame(7, (int) $eventRef->getEventId());
        self::assertSame(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, $eventRef->getEventType());
    }

    public function testParsesPascalCaseRequestIdReference(): void
    {
        $uri = 'temporal:///namespaces/default/workflows/wf/run/history'
            . '?referenceType=RequestIdReference&requestID=req-1&eventType=WorkflowExecutionOptionsUpdated';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        self::assertCount(1, $result);
        $ref = $result[0]->getWorkflowEvent()->getRequestIdRef();
        self::assertNotNull($ref);
        self::assertSame('req-1', $ref->getRequestId());
        self::assertSame(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_OPTIONS_UPDATED, $ref->getEventType());
    }

    public function testParsesNexusOperationScheduledPascalCase(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history'
            . '?referenceType=EventReference&eventType=NexusOperationScheduled';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        $eventRef = $result[0]->getWorkflowEvent()->getEventRef();
        self::assertSame(EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED, $eventRef->getEventType());
    }

    public function testRejectsUnknownPascalCase(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history'
            . '?referenceType=EventReference&eventType=WorkflowDoesNotExist';
        $this->expectException(InvalidArgumentException::class);
        NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);
    }

    public function testRejectsLowerCaseEventType(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history'
            . '?referenceType=EventReference&eventType=workflowExecutionStarted';
        $this->expectException(InvalidArgumentException::class);
        NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);
    }

    public function testRejectsEmptyEventType(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history?referenceType=EventReference&eventType=';
        $this->expectException(InvalidArgumentException::class);
        NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);
    }

    public function testParsesJavaWireFormatVerbatim(): void
    {
        // Fixture from sdk-java LinkConverterTest::testConvertWorkflowEventToNexus_Valid
        $uri = 'temporal:///namespaces/ns/workflows/wf-id/run-id/history'
            . '?referenceType=EventReference&eventID=1&eventType=WorkflowExecutionStarted';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        $event = $result[0]->getWorkflowEvent();
        self::assertSame('ns', $event->getNamespace());
        self::assertSame('wf-id', $event->getWorkflowId());
        self::assertSame('run-id', $event->getRunId());
        self::assertSame(1, (int) $event->getEventRef()->getEventId());
        self::assertSame(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, $event->getEventRef()->getEventType());
    }

    public function testParsesGoWireFormatVerbatim(): void
    {
        $uri = 'temporal:///namespaces/ns/workflows/wf-id/run-id/history'
            . '?referenceType=EventReference&eventID=1&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $result = NexusLinkConverter::toProtoLinks([new NexusLink($uri, self::TYPE)]);

        self::assertSame(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, $result[0]->getWorkflowEvent()->getEventRef()->getEventType());
    }

    // ---- Encoder (Part B) ----

    public function testEncodesToJavaWireFormat(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('ns')
            ->setWorkflowId('wf-id')
            ->setRunId('run-id');
        $event->setEventRef(
            (new EventReference())
                ->setEventId(1)
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
        );

        $link = NexusLinkConverter::workflowEventToNexusLink($event);

        self::assertSame(self::TYPE, $link->type);
        self::assertSame(
            'temporal:///namespaces/ns/workflows/wf-id/run-id/history'
            . '?referenceType=EventReference&eventID=1&eventType=WorkflowExecutionStarted',
            $link->uri,
        );
    }

    public function testEncodesRequestIdReferenceToJavaWireFormat(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('ns')
            ->setWorkflowId('wf-id')
            ->setRunId('run-id');
        $event->setRequestIdRef(
            (new RequestIdReference())
                ->setRequestId('random-request-id')
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_OPTIONS_UPDATED),
        );

        $link = NexusLinkConverter::workflowEventToNexusLink($event);

        self::assertSame(
            'temporal:///namespaces/ns/workflows/wf-id/run-id/history'
            . '?referenceType=RequestIdReference&requestID=random-request-id&eventType=WorkflowExecutionOptionsUpdated',
            $link->uri,
        );
    }

    public function testRoundTripEventReferenceWithEventId(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('ns')
            ->setWorkflowId('wf')
            ->setRunId('run');
        $event->setEventRef(
            (new EventReference())
                ->setEventId(42)
                ->setEventType(EventType::EVENT_TYPE_NEXUS_OPERATION_STARTED),
        );

        $link = NexusLinkConverter::workflowEventToNexusLink($event);
        $decoded = NexusLinkConverter::toProtoLinks([$link])[0]->getWorkflowEvent();

        self::assertSame('ns', $decoded->getNamespace());
        self::assertSame('wf', $decoded->getWorkflowId());
        self::assertSame('run', $decoded->getRunId());
        self::assertSame(42, (int) $decoded->getEventRef()->getEventId());
        self::assertSame(
            EventType::EVENT_TYPE_NEXUS_OPERATION_STARTED,
            $decoded->getEventRef()->getEventType(),
        );
    }

    public function testRoundTripRequestIdReference(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('default')
            ->setWorkflowId('wf')
            ->setRunId('run');
        $event->setRequestIdRef(
            (new RequestIdReference())
                ->setRequestId('req-abc')
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_OPTIONS_UPDATED),
        );

        $link = NexusLinkConverter::workflowEventToNexusLink($event);
        $decoded = NexusLinkConverter::toProtoLinks([$link])[0]->getWorkflowEvent();

        self::assertSame('req-abc', $decoded->getRequestIdRef()->getRequestId());
        self::assertSame(
            EventType::EVENT_TYPE_WORKFLOW_EXECUTION_OPTIONS_UPDATED,
            $decoded->getRequestIdRef()->getEventType(),
        );
    }

    public function testRoundTripEventReferenceWithoutEventId(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('ns')
            ->setWorkflowId('wf')
            ->setRunId('run');
        $event->setEventRef(
            (new EventReference())
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
        );

        $link = NexusLinkConverter::workflowEventToNexusLink($event);
        self::assertStringNotContainsString('eventID=', $link->uri);

        $decoded = NexusLinkConverter::toProtoLinks([$link])[0]->getWorkflowEvent();
        self::assertSame(0, (int) $decoded->getEventRef()->getEventId());
    }

    public function testEncodesSlashInWorkflowId(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('ns')
            ->setWorkflowId('wf-id/')
            ->setRunId('run-id');
        $event->setEventRef(
            (new EventReference())
                ->setEventId(1)
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
        );

        $link = NexusLinkConverter::workflowEventToNexusLink($event);

        self::assertStringContainsString('/workflows/wf-id%2F/', $link->uri);
    }

    public function testEncodesAngleInWorkflowId(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('ns')
            ->setWorkflowId('wf-id>')
            ->setRunId('run-id');
        $event->setEventRef(
            (new EventReference())
                ->setEventId(1)
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
        );

        $link = NexusLinkConverter::workflowEventToNexusLink($event);

        self::assertStringContainsString('/workflows/wf-id%3E/', $link->uri);
    }

    public function testEncoderRejectsEmptyWorkflowEvent(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('ns')
            ->setWorkflowId('wf')
            ->setRunId('run');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/event_ref or request_id_ref/');
        NexusLinkConverter::workflowEventToNexusLink($event);
    }

    public function testEncoderRejectsUnknownEventTypeEnumValue(): void
    {
        $event = (new WorkflowEvent())
            ->setNamespace('ns')
            ->setWorkflowId('wf')
            ->setRunId('run');
        $event->setEventRef(
            (new EventReference())->setEventType(99999),
        );

        $this->expectException(InvalidArgumentException::class);
        NexusLinkConverter::workflowEventToNexusLink($event);
    }

    public function testHardcodedTypeWorkflowEventConstant(): void
    {
        // Tripwire. The proto-php stubs we depend on don't expose a stable
        // descriptor.full_name accessor on generated classes today, so we
        // hardcode the type string in NexusLinkConverter::TYPE_WORKFLOW_EVENT.
        // If proto-php gains such an accessor (e.g. via descriptor pool),
        // replace the hardcode and update this test.
        self::assertSame(
            'temporal.api.common.v1.Link.WorkflowEvent',
            NexusLinkConverter::TYPE_WORKFLOW_EVENT,
        );
        self::assertTrue(\class_exists(WorkflowEvent::class));
    }

    public function testEncoderOutputIsAcceptedByOurDecoder(): void
    {
        $cases = [
            // (namespace, workflowId, runId, fn(): WorkflowEvent)
            [
                'ns', 'wf', 'run',
                static fn(): EventReference => (new EventReference())
                    ->setEventId(1)
                    ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
                'event',
            ],
            [
                'default', 'wf-x', 'run-y',
                static fn(): EventReference => (new EventReference())
                    ->setEventType(EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED),
                'event',
            ],
            [
                'ns space', 'wf/with/slash', 'run-1',
                static fn(): RequestIdReference => (new RequestIdReference())
                    ->setRequestId('rid-1')
                    ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
                'request',
            ],
            [
                'ns', 'wf', 'run',
                static fn(): EventReference => (new EventReference())
                    ->setEventId(0)
                    ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
                'event',
            ],
        ];

        foreach ($cases as $i => [$ns, $wfId, $runId, $refFactory, $kind]) {
            $event = (new WorkflowEvent())
                ->setNamespace($ns)
                ->setWorkflowId($wfId)
                ->setRunId($runId);
            $ref = $refFactory();
            if ($kind === 'event') {
                $event->setEventRef($ref);
            } else {
                $event->setRequestIdRef($ref);
            }

            $link = NexusLinkConverter::workflowEventToNexusLink($event);
            $decoded = NexusLinkConverter::toProtoLinks([$link])[0]->getWorkflowEvent();

            self::assertSame($ns, $decoded->getNamespace(), "case #$i: namespace");
            self::assertSame($wfId, $decoded->getWorkflowId(), "case #$i: workflowId");
            self::assertSame($runId, $decoded->getRunId(), "case #$i: runId");
        }
    }

    public function testToNexusProtoLinksPassesUrlAndTypeThrough(): void
    {
        $uri = 'temporal:///namespaces/default/workflows/wf/run/history'
            . '?referenceType=EventReference&eventType=WorkflowExecutionStarted';
        $result = NexusLinkConverter::toNexusProtoLinks([new NexusLink($uri, self::TYPE)]);

        self::assertCount(1, $result);
        self::assertSame($uri, $result[0]->getUrl());
        self::assertSame(self::TYPE, $result[0]->getType());
    }

    public function testToNexusProtoLinksKeepsCustomLinkTypes(): void
    {
        $custom = new NexusLink('https://example.com/x', 'custom.user.type');
        $event = new NexusLink(
            'temporal:///namespaces/n/workflows/w/r/history?referenceType=EventReference&eventType=WorkflowExecutionStarted',
            self::TYPE,
        );

        $result = NexusLinkConverter::toNexusProtoLinks([$custom, $event]);

        self::assertCount(2, $result);
        self::assertSame('custom.user.type', $result[0]->getType());
        self::assertSame(self::TYPE, $result[1]->getType());
    }

    public function testToNexusProtoLinksReturnsEmptyArrayForEmptyInput(): void
    {
        self::assertSame([], NexusLinkConverter::toNexusProtoLinks([]));
    }
}
