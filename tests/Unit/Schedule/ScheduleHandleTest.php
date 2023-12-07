<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule;

use Spiral\Attributes\AttributeReader;
use Temporal\Api\Schedule\V1\BackfillRequest;
use Temporal\Api\Workflowservice\V1\DeleteScheduleRequest;
use Temporal\Api\Workflowservice\V1\DeleteScheduleResponse;
use Temporal\Api\Workflowservice\V1\PatchScheduleRequest;
use Temporal\Api\Workflowservice\V1\PatchScheduleResponse;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\Schedule\BackfillPeriod;
use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\ScheduleHandle;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;

/**
 * @covers \Temporal\Client\Schedule\ScheduleHandle
 */
class ScheduleHandleTest extends TestCase
{
    public function getId(): void
    {
        $scheduleHandle = $this->createScheduleHandle(id: 'test-id-test');

        $this->assertSame('test-id-test', $scheduleHandle->getId());
    }

    /**
     * Test that the delete method calls the correct GRPC method with the correct arguments.
     */
    public function testDelete(): void
    {
        $testContext = new class {
            public DeleteScheduleRequest $request;
        };
        // Prepare mocks
        $clientMock = $this->createMock(ServiceClientInterface::class);
        $clientMock->expects($this->once())
            ->method('DeleteSchedule')
            ->with($this->callback(fn (DeleteScheduleRequest $request) => $testContext->request = $request or true))
            ->willReturn(new DeleteScheduleResponse());

        $scheduleHandle = $this->createScheduleHandle(
            client: $clientMock,
        );

        $scheduleHandle->delete();

        $this->assertTrue(isset($testContext->request));
        $this->assertSame('default', $testContext->request->getNamespace());
        $this->assertSame('test-id', $testContext->request->getScheduleId());
        $this->assertSame('test-identity', $testContext->request->getIdentity());
    }

    public function testPause(): void
    {
        $testContext = new class {
            public PatchScheduleRequest $request;
        };
        // Prepare mocks
        $clientMock = $this->createMock(ServiceClientInterface::class);
        $clientMock->expects($this->once())
            ->method('PatchSchedule')
            ->with($this->callback(fn (PatchScheduleRequest $request) => $testContext->request = $request or true))
            ->willReturn(new PatchScheduleResponse());

        $scheduleHandle = $this->createScheduleHandle(
            client: $clientMock,
        );

        $scheduleHandle->pause();

        $this->assertTrue(isset($testContext->request));
        $this->assertSame('default', $testContext->request->getNamespace());
        $this->assertSame('test-id', $testContext->request->getScheduleId());
        $this->assertSame('Paused via PHP SDK', $testContext->request->getPatch()->getPause());
    }

    public function testPauseWithWrongArgument(): void
    {
        $scheduleHandle = $this->createScheduleHandle();

        $this->expectException(InvalidArgumentException::class);

        $scheduleHandle->pause('');
    }

    public function testUnpause(): void
    {
        $testContext = new class {
            public PatchScheduleRequest $request;
        };
        // Prepare mocks
        $clientMock = $this->createMock(ServiceClientInterface::class);
        $clientMock->expects($this->once())
            ->method('PatchSchedule')
            ->with($this->callback(fn (PatchScheduleRequest $request) => $testContext->request = $request or true))
            ->willReturn(new PatchScheduleResponse());

        $scheduleHandle = $this->createScheduleHandle(
            client: $clientMock,
        );

        $scheduleHandle->unpause();

        $this->assertTrue(isset($testContext->request));
        $this->assertSame('default', $testContext->request->getNamespace());
        $this->assertSame('test-id', $testContext->request->getScheduleId());
        $this->assertSame('Unpaused via PHP SDK', $testContext->request->getPatch()->getUnpause());
    }

    public function testUnpauseWithWrongArgument(): void
    {
        $scheduleHandle = $this->createScheduleHandle();

        $this->expectException(InvalidArgumentException::class);

        $scheduleHandle->unpause('');
    }

    /**
     * Test that the backfill method calls the correct GRPC method with the correct arguments.
     */
    public function testBackfill(): void
    {
        $testContext = new class {
            public PatchScheduleRequest $request;
        };
        // Prepare mocks
        $clientMock = $this->createMock(ServiceClientInterface::class);
        $clientMock->expects($this->once())
            ->method('PatchSchedule')
            ->with($this->callback(fn (PatchScheduleRequest $request) => $testContext->request = $request or true))
            ->willReturn(new PatchScheduleResponse());
        $scheduleHandle = $this->createScheduleHandle(
            client: $clientMock,
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

    public function testBackfillWithWrongArguments(): void
    {
        $scheduleHandle = $this->createScheduleHandle();

        $this->expectException(InvalidArgumentException::class);

        $scheduleHandle->backfill([
            BackfillPeriod::new('2021-01-03', '2021-01-04', ScheduleOverlapPolicy::BufferAll),
            new \stdClass(),
        ]);
    }

    private function createScheduleHandle(
        ?ServiceClientInterface $client = null,
        ?ClientOptions $clientOptions = null,
        ?DataConverterInterface $converter = null,
        ?MarshallerInterface $marshaller = null,
        string $namespace = 'default',
        string $id = 'test-id',
    ): ScheduleHandle
    {
        if ($clientOptions === null) {
            $clientOptions = $this->createMock(ClientOptions::class);
            $clientOptions->identity = 'test-identity';
        }
        return new ScheduleHandle(
            $client ?? $this->createMock(ServiceClientInterface::class),
            $clientOptions,
            $converter = ($converter ?? DataConverter::createDefault()),
            $marshaller ?? new Marshaller(new AttributeMapperFactory(new AttributeReader())),
            new ProtoToArrayConverter($converter),
            $namespace,
            $id,
        );
    }
}
