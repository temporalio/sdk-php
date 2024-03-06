<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Spiral\Attributes\AttributeReader;
use Temporal\Api\Workflowservice\V1\CreateScheduleRequest;
use Temporal\Api\Workflowservice\V1\CreateScheduleResponse;
use Temporal\Api\Workflowservice\V1\ListSchedulesRequest;
use Temporal\Api\Workflowservice\V1\ListSchedulesResponse;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\Policy\SchedulePolicies;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\Spec\CalendarSpec;
use Temporal\Client\Schedule\Spec\Range;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\ScheduleState;
use Temporal\Client\Schedule\Spec\StructuredCalendarSpec;
use Temporal\Client\ScheduleClient;
use Temporal\Common\IdReusePolicy as WorkflowIdReusePolicy;
use Temporal\Common\RetryOptions;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;
use Temporal\Tests\TestCase;

#[CoversClass(\Temporal\Client\ScheduleClient::class)]
class ScheduleClientTestCase extends TestCase
{
    public function testCreateSchedule(): void
    {
        $testContext = new class {
            public CreateScheduleRequest $request;
        };
        // Prepare mocks
        $clientMock = $this->createMock(ServiceClientInterface::class);
        $clientMock->expects($this->once())
            ->method('CreateSchedule')
            ->with($this->callback(fn(CreateScheduleRequest $request) => $testContext->request = $request or true))
            ->willReturn((new CreateScheduleResponse())->setConflictToken('test-conflict-token'));
        $scheduleClient = $this->createScheduleClient(
            client: $clientMock,
        );
        $scheduleDto = $this->getScheduleDto();

        $result = $scheduleClient->createSchedule($scheduleDto, scheduleId: 'test-id');

        $this->assertTrue(isset($testContext->request));
        $this->assertSame('default', $testContext->request->getNamespace());
        $this->assertSame('test-id', $testContext->request->getScheduleId());
        $this->assertSame('test-id', $result->getID());
    }

    public function testListSchedules(): void
    {
        $testContext = new class {
            public ListSchedulesRequest $request;
        };
        // Prepare mocks
        $clientMock = $this->createMock(ServiceClientInterface::class);
        $clientMock->expects($this->once())
            ->method('ListSchedules')
            ->with($this->callback(fn(ListSchedulesRequest $request) => $testContext->request = $request or true))
            ->willReturn((new ListSchedulesResponse()));
        $scheduleClient = $this->createScheduleClient(
            client: $clientMock,
        );

        $scheduleClient->listSchedules('default-namespace', 150);

        $this->assertTrue(isset($testContext->request));
        $this->assertSame('default-namespace', $testContext->request->getNamespace());
        $this->assertSame(150, $testContext->request->getMaximumPageSize());
    }

    public function testScheduleMarshalling(): void
    {
        $converter = DataConverter::createDefault();
        $protoConverter = new ProtoToArrayConverter($converter);
        $marshaller = new Marshaller(
            new AttributeMapperFactory(new AttributeReader()),
        );
        $testContext = new class {
            public CreateScheduleRequest $request;
        };
        // Prepare mocks
        $clientMock = $this->createMock(ServiceClientInterface::class);
        $clientMock->expects($this->once())
            ->method('CreateSchedule')
            ->with($this->callback(fn(CreateScheduleRequest $request) => $testContext->request = $request or true))
            ->willReturn((new CreateScheduleResponse())->setConflictToken('test-conflict-token'));
        $scheduleClient = $this->createScheduleClient(
            client: $clientMock,
            converter: $converter,
        );
        $initScheduleDto = $this->getScheduleDto();

        $scheduleClient->createSchedule($initScheduleDto);
        $this->assertTrue(isset($testContext->request));
        $protoSchedule = $testContext->request->getSchedule();

        $rehydrated = (new \ReflectionClass(Schedule::class))->newInstanceWithoutConstructor();
        $values = $protoConverter->convert($protoSchedule);

        $rehydrated = $marshaller->unmarshal($values, $rehydrated);

        // Compare Specs
        $this->assertEquals($initScheduleDto->spec->timezoneName, $rehydrated->spec->timezoneName);
        $this->assertEquals($initScheduleDto->spec->startTime, $rehydrated->spec->startTime);
        $this->assertEquals($initScheduleDto->spec->endTime, $rehydrated->spec->endTime);
        $this->assertEqualIntervals($initScheduleDto->spec->jitter, $rehydrated->spec->jitter);
        $this->assertEquals($initScheduleDto->spec->calendarList, $rehydrated->spec->calendarList);
        $this->assertEquals($initScheduleDto->spec->cronStringList, $rehydrated->spec->cronStringList);
        $this->assertEquals($initScheduleDto->spec->structuredCalendarList, $rehydrated->spec->structuredCalendarList);
        $this->assertEquals($initScheduleDto->spec->excludeCalendarList, $rehydrated->spec->excludeCalendarList);
        $this->assertEquals($initScheduleDto->spec->intervalList, $rehydrated->spec->intervalList);
        $this->assertEquals($initScheduleDto->spec->timezoneData, $rehydrated->spec->timezoneData);
        $this->assertEquals($initScheduleDto->spec->excludeStructuredCalendarList, $rehydrated->spec->excludeStructuredCalendarList);


        $this->assertEquals($initScheduleDto->state, $rehydrated->state);
        $this->assertEquals($initScheduleDto->policies->overlapPolicy, $rehydrated->policies->overlapPolicy);
        $this->assertEqualIntervals($initScheduleDto->policies->catchupWindow, $rehydrated->policies->catchupWindow);
        $this->assertEquals($initScheduleDto->policies->pauseOnFailure, $rehydrated->policies->pauseOnFailure);
        // Compare each Action property individually
        $action0 = $initScheduleDto->action;
        $action1 = $rehydrated->action;
        $this->assertSame($action0->workflowId, $action1->workflowId);
        $this->assertEquals($action0->workflowType, $action1->workflowType);
        $this->assertEquals($action0->taskQueue, $action1->taskQueue);
        $this->assertSame($action0->input->getValues(), $action1->input->getValues());
        $this->assertEqualIntervals($action0->workflowExecutionTimeout, $action1->workflowExecutionTimeout);
        $this->assertEqualIntervals($action0->workflowRunTimeout, $action1->workflowRunTimeout);
        $this->assertEqualIntervals($action0->workflowTaskTimeout, $action1->workflowTaskTimeout);
        $this->assertSame($action0->workflowIdReusePolicy, $action1->workflowIdReusePolicy);
        $this->assertSame($action0->retryPolicy->initialInterval, $action1->retryPolicy->initialInterval);
        $this->assertSame($action0->retryPolicy->backoffCoefficient, $action1->retryPolicy->backoffCoefficient);
        $this->assertSame($action0->retryPolicy->maximumInterval, $action1->retryPolicy->maximumInterval);
        $this->assertSame($action0->retryPolicy->maximumAttempts, $action1->retryPolicy->maximumAttempts);
        $this->assertSame($action0->retryPolicy->nonRetryableExceptions, $action1->retryPolicy->nonRetryableExceptions);
        $this->assertSame($action0->memo->getValues(), $action1->memo->getValues());
        $this->assertSame($action0->searchAttributes->getValues(), $action1->searchAttributes->getValues());
        $this->assertSame($action0->header->getValues(), $action1->header->getValues());
    }

    public function testGetHandle(): void
    {
        $scheduleClient = $this->createScheduleClient();

        $result = $scheduleClient->getHandle('test-id', 'test-namespace');

        $this->assertSame('test-id', $result->getID());
    }

    /**
     * Create a ScheduleClient with mocked dependencies
     */
    private function createScheduleClient(
        ?ServiceClientInterface $client = null,
        ?ClientOptions $clientOptions = null,
        ?DataConverterInterface $converter = null,
    ): ScheduleClient {
        if ($clientOptions === null) {
            $clientOptions = $this->createMock(ClientOptions::class);
            $clientOptions->identity = 'test-identity';
        }
        return ScheduleClient::create(
            $client ?? $this->createMock(ServiceClientInterface::class),
            $clientOptions,
            $converter ?? DataConverter::createDefault(),
        );
    }

    /**
     * Prepare a rich Schedule DTO
     */
    private function getScheduleDto(): Schedule
    {
        return Schedule::new()->withAction(
            StartWorkflowAction::new('PingSite')
                ->withInput(['google.com'])
                ->withTaskQueue('default')
                ->withRetryPolicy(RetryOptions::new()->withMaximumAttempts(3))
                ->withHeader(['foo' => 'bar'])
                ->withWorkflowExecutionTimeout('40m')
                ->withWorkflowRunTimeout('30m')
                ->withWorkflowTaskTimeout('10m')
                ->withMemo(['foo' => 'memo'])
                ->withSearchAttributes(['foo' => 'search'])
                ->withWorkflowId('workflow-id')
                ->withWorkflowIdReusePolicy(WorkflowIdReusePolicy::AllowDuplicateFailedOnly)
        )->withSpec(
            ScheduleSpec::new()
                ->withStructuredCalendarList(
                    StructuredCalendarSpec::new()
                        ->withDaysOfWeek(Range::new(1, 5))
                        ->withHours(Range::new(9, 9), Range::new(12, 12))
                )
                ->withCalendarList(
                    CalendarSpec::new()
                        ->withSecond(6)
                        ->withMinute('*/6')
                        ->withComment('test comment')
                )
                ->withCronStringList('0 12 * * 5', '0 12 * * 1')
                ->withStartTime(new DateTimeImmutable('2024-10-01T00:00:00Z'))
                ->withEndTime(new DateTimeImmutable('2024-10-31T00:00:00Z'))
                ->withJitter('10m')
                ->withTimezoneName('UTC')
        )->withPolicies(SchedulePolicies::new()
            ->withCatchupWindow('10m')
            ->withPauseOnFailure(true)
            ->withOverlapPolicy(ScheduleOverlapPolicy::CancelOther)
        )->withState(
            ScheduleState::new()
                ->withLimitedActions(true)
                ->withRemainingActions(10)
                ->withPaused(true)
                ->withNotes('test notes')
        );
    }
}
