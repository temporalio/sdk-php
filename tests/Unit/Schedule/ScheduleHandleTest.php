<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule;

use Spiral\Attributes\AttributeReader;
use Temporal\Api\Schedule\V1\BackfillRequest;
use Temporal\Api\Workflowservice\V1\PatchScheduleRequest;
use Temporal\Api\Workflowservice\V1\PatchScheduleResponse;
use Temporal\Client\Schedule\BackfillPeriod;
use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\ScheduleHandle;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;

/**
 * @covers \Temporal\Client\Schedule\ScheduleHandle
 */
class ScheduleHandleTest extends TestCase
{
    /**
     * Test that the backfill method calls the correct GRPC method with the correct arguments.
     */
    public function testBackfill()
    {
        $testContext = new class {
            public PatchScheduleRequest $request;
        };
        // Prepare mocks
        $clientMock = $this->createMock(\Temporal\Client\GRPC\ServiceClientInterface::class);
        $clientMock->expects($this->once())
            ->method('PatchSchedule')
            ->with($this->callback(fn (PatchScheduleRequest $request) => $testContext->request = $request or true))
            ->willReturn(new PatchScheduleResponse());
        $optionsMock = $this->createMock(\Temporal\Client\ClientOptions::class);
        $scheduleHandle = new ScheduleHandle(
            $clientMock,
            $optionsMock,
            $converter = DataConverter::createDefault(),
            new Marshaller(new AttributeMapperFactory(new AttributeReader())),
            new ProtoToArrayConverter($converter),
            'default',
            'test-id',
        );

        $scheduleHandle->backfill([
            BackfillPeriod::new('2021-01-01', '2021-01-02', ScheduleOverlapPolicy::CancelOther),
            BackfillPeriod::new('2021-01-03', '2021-01-04', ScheduleOverlapPolicy::BufferAll),
        ]);

        $this->assertTrue(isset($testContext->request));
        $this->assertSame('default', $testContext->request->getNamespace());
        $this->assertSame('test-id', $testContext->request->getScheduleId());
        // Test BackfillRequest
        $backfills = $testContext->request->getPatch()->getBackfillRequest();
        $this->assertCount(2, $backfills);
        $backfill0 = $backfills[0];
        $backfill1 = $backfills[1];
        $this->assertInstanceOf(BackfillRequest::class, $backfill0);
        $this->assertInstanceOf(BackfillRequest::class, $backfill1);
        $this->assertSame('2021-01-01', $backfill0->getStartTime()->toDateTime()->format('Y-m-d'));
        $this->assertSame('2021-01-02', $backfill0->getEndTime()->toDateTime()->format('Y-m-d'));
        $this->assertSame(ScheduleOverlapPolicy::CancelOther->value, $backfill0->getOverlapPolicy());
        $this->assertSame('2021-01-03', $backfill1->getStartTime()->toDateTime()->format('Y-m-d'));
        $this->assertSame('2021-01-04', $backfill1->getEndTime()->toDateTime()->format('Y-m-d'));
        $this->assertSame(ScheduleOverlapPolicy::BufferAll->value, $backfill1->getOverlapPolicy());
    }


}
