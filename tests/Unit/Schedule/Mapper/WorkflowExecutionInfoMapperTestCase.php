<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spiral\Attributes\AttributeReader;
use Carbon\CarbonInterval;
use Temporal\Api\Enums\V1\ScheduleOverlapPolicy;
use Temporal\Api\Workflow\V1\NewWorkflowExecutionInfo;
use Temporal\Api\Schedule\V1\CalendarSpec as V1CalendarSpec;
use Temporal\Api\Schedule\V1\IntervalSpec as V1IntervalSpec;
use Temporal\Api\Schedule\V1\Schedule as V1Schedule;
use Temporal\Api\Schedule\V1\ScheduleAction as V1ScheduleAction;
use Temporal\Api\Schedule\V1\SchedulePolicies as V1SchedulePolicies;
use Temporal\Api\Schedule\V1\ScheduleSpec as V1ScheduleSpec;
use Temporal\Api\Schedule\V1\ScheduleState as V1ScheduleState;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy as ClientScheduleOverlapPolicy;
use Temporal\Client\Schedule\Policy\SchedulePolicies;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\Spec\CalendarSpec;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\ScheduleState;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\RetryOptions;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Mapper\ScheduleMapper;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;

#[CoversClass(\Temporal\Internal\Mapper\ScheduleMapper::class)]
final class WorkflowExecutionInfoMapperTestCase extends TestCase
{
    private DataConverterInterface $dataConverter;

    public function testToMessage(): void
    {
        $mapper = $this->createMapper();

        $schedule = $mapper->toMessage(
            Schedule::new()->withAction(
                StartWorkflowAction::new('PingSite')
                    ->withInput(['google.com'])
                    ->withTaskQueue('default')
                    ->withRetryPolicy(
                        RetryOptions::new()
                            ->withMaximumAttempts(3)
                            ->withInitialInterval(CarbonInterval::seconds(10))
                            ->withMaximumInterval(CarbonInterval::seconds(20)),
                    )
                    ->withHeader(['foo' => 'bar'])
                    ->withWorkflowExecutionTimeout('40m')
                    ->withWorkflowRunTimeout('30m')
                    ->withWorkflowTaskTimeout('10m')
                    ->withMemo(['memo1' => 'memo-value1', 'memo2' => 'memo-value2'])
                    ->withSearchAttributes(['sAttr1' => 's-value1', 'sAttr2' => 's-value2'])
                    ->withWorkflowId('test-workflow-id')
                    ->withWorkflowIdReusePolicy(
                        IdReusePolicy::AllowDuplicateFailedOnly,
                    ),
            )->withSpec(
                ScheduleSpec::new()
                    ->withCalendarList(
                        CalendarSpec::new()
                            ->withSecond(6)->withMinute('*/6')->withHour('*/5')
                            ->withDayOfWeek('*/2')->withDayOfMonth('*/4')->withMonth('*/3')
                            ->withComment('test comment'),
                    )
                    ->withCronStringList('0 12 * * 5', '0 12 * * 1')
                    ->withIntervalList('2m', '3m')
                    ->withStartTime((new \DateTimeImmutable())->setTimestamp(172800))
                    ->withEndTime((new \DateTimeImmutable())->setTimestamp(2678400))
                    ->withJitter('10m')
                    ->withTimezoneData('America/New_York')
                    ->withTimezoneName('Europe/London'),
            )->withPolicies(
                SchedulePolicies::new()
                    ->withCatchupWindow('10m')
                    ->withPauseOnFailure(true)
                    ->withOverlapPolicy(ClientScheduleOverlapPolicy::CancelOther),
            )->withState(
                ScheduleState::new()
                    ->withLimitedActions(true)
                    ->withRemainingActions(10)
                    ->withPaused(true)
                    ->withNotes('test notes'),
            ),
        );

        $this->assertInstanceOf(V1Schedule::class, $schedule);
        $spec = $schedule->getSpec();
        $state = $schedule->getState();
        $policies = $schedule->getPolicies();
        $this->assertInstanceOf(V1ScheduleSpec::class, $spec);
        $this->assertInstanceOf(V1ScheduleState::class, $state);
        $this->assertInstanceOf(V1SchedulePolicies::class, $policies);

        // Test Action
        $this->assertInstanceOf(V1ScheduleAction::class, $schedule->getAction());
        $this->assertSame('start_workflow', $schedule->getAction()->getAction());
        $startWorkflow = $schedule->getAction()->getStartWorkflow();
        $this->assertInstanceOf(NewWorkflowExecutionInfo::class, $startWorkflow);
        $this->assertSame('PingSite', $startWorkflow->getWorkflowType()->getName());
        $this->assertSame('default', $startWorkflow->getTaskQueue()->getName());
        // Retry Policy
        $this->assertSame(3, $startWorkflow->getRetryPolicy()->getMaximumAttempts());
        $this->assertSame(10, $startWorkflow->getRetryPolicy()->getInitialInterval()->getSeconds());
        $this->assertSame(20, $startWorkflow->getRetryPolicy()->getMaximumInterval()->getSeconds());
        // Header
        $this->assertSame(
            ['foo' => 'bar'],
            EncodedCollection::fromPayloadCollection(
                $startWorkflow->getHeader()->getFields(),
                $this->dataConverter,
            )->getValues(),
        );
        $this->assertEquals(
            ['memo1' => 'memo-value1', 'memo2' => 'memo-value2'],
            EncodedCollection::fromPayloadCollection(
                $startWorkflow->getMemo()->getFields(),
                $this->dataConverter,
            )->getValues(),
        );
        $this->assertEquals(
            ['sAttr1' => 's-value1', 'sAttr2' => 's-value2'],
            EncodedCollection::fromPayloadCollection(
                $startWorkflow->getSearchAttributes()->getIndexedFields(),
                $this->dataConverter,
            )->getValues(),
        );
        $this->assertSame('test-workflow-id', $startWorkflow->getWorkflowId());
        $this->assertSame(
            IdReusePolicy::AllowDuplicateFailedOnly->value,
            $startWorkflow->getWorkflowIdReusePolicy(),
        );

        // Test Spec
        // Calendar
        $dto = $spec->getCalendar()[0];
        $this->assertInstanceOf(V1CalendarSpec::class, $dto);
        $this->assertEquals('test comment', $dto->getComment());
        $this->assertEquals(6, $dto->getSecond());
        $this->assertEquals('*/6', $dto->getMinute());
        $this->assertEquals('*/5', $dto->getHour());
        $this->assertEquals('*/2', $dto->getDayOfWeek());
        $this->assertEquals('*/4', $dto->getDayOfMonth());
        $this->assertEquals('*/3', $dto->getMonth());
        // Cron
        $this->assertEquals('0 12 * * 5', $spec->getCronString()[0]);
        $this->assertEquals('0 12 * * 1', $spec->getCronString()[1]);
        // Interval
        $dto = $spec->getInterval()[0];
        $this->assertInstanceOf(V1IntervalSpec::class, $dto);
        $this->assertEquals(120, $dto->getInterval()->getSeconds());
        $dto = $spec->getInterval()[1];
        $this->assertInstanceOf(V1IntervalSpec::class, $dto);
        $this->assertEquals(180, $dto->getInterval()->getSeconds());
        // StartTime and StopTime
        $this->assertEquals(172800, $spec->getStartTime()->getSeconds());
        $this->assertEquals(2678400, $spec->getEndTime()->getSeconds());
        // Jitter
        $this->assertEquals(600, $spec->getJitter()->getSeconds());
        // Timezone
        $this->assertEquals('America/New_York', $spec->getTimezoneData());
        $this->assertEquals('Europe/London', $spec->getTimezoneName());

        // Test Policies
        $this->assertEquals(600, $policies->getCatchupWindow()->getSeconds());
        $this->assertTrue($policies->getPauseOnFailure());
        $this->assertEquals(ScheduleOverlapPolicy::SCHEDULE_OVERLAP_POLICY_CANCEL_OTHER, $policies->getOverlapPolicy());

        // Test State
        $this->assertTrue($state->getLimitedActions());
        $this->assertEquals(10, $state->getRemainingActions());
        $this->assertTrue($state->getPaused());
        $this->assertEquals('test notes', $state->getNotes());
    }

    public function testToMessageEmptyValues(): void
    {
        $mapper = $this->createMapper();

        $schedule = $mapper->toMessage(
            Schedule::new()->withAction(
                StartWorkflowAction::new('PingSite'),
            ),
        );

        $this->assertInstanceOf(V1Schedule::class, $schedule);
        $spec = $schedule->getSpec();
        $state = $schedule->getState();
        $policies = $schedule->getPolicies();
        $this->assertInstanceOf(V1ScheduleSpec::class, $spec);
        $this->assertInstanceOf(V1ScheduleState::class, $state);
        $this->assertInstanceOf(V1SchedulePolicies::class, $policies);

        // Test Action
        $this->assertInstanceOf(V1ScheduleAction::class, $schedule->getAction());
        $this->assertSame('start_workflow', $schedule->getAction()->getAction());
        $startWorkflow = $schedule->getAction()->getStartWorkflow();
        $this->assertInstanceOf(NewWorkflowExecutionInfo::class, $startWorkflow);
        $this->assertSame('PingSite', $startWorkflow->getWorkflowType()->getName());
        $this->assertSame('default', $startWorkflow->getTaskQueue()->getName());
        // Retry Policy
        $this->assertSame(0, $startWorkflow->getRetryPolicy()->getMaximumAttempts());
        $this->assertNull($startWorkflow->getRetryPolicy()->getInitialInterval());
        $this->assertNull($startWorkflow->getRetryPolicy()->getMaximumInterval());
        // Header
        $this->assertSame(0, $startWorkflow->getHeader()->getFields()->count());
        $this->assertSame(0, $startWorkflow->getMemo()->getFields()->count());
        $this->assertSame(0, $startWorkflow->getSearchAttributes()->getIndexedFields()->count());
        $this->assertNotEmpty($startWorkflow->getWorkflowId());
        $this->assertSame(IdReusePolicy::Unspecified->value, $startWorkflow->getWorkflowIdReusePolicy());

        // Test Spec
        // Calendar
        $this->assertEmpty($spec->getCalendar());
        // Cron
        $this->assertEmpty($spec->getCronString());
        // Interval
        $this->assertEmpty($spec->getInterval());
        // StartTime and StopTime
        $this->assertNull($spec->getStartTime());
        $this->assertNull($spec->getEndTime());
        // Jitter
        $this->assertEquals(0, $spec->getJitter()->getSeconds());
        // Timezone
        $this->assertEmpty($spec->getTimezoneData());
        $this->assertEmpty($spec->getTimezoneName());

        // Test Policies
        $this->assertEquals(60, $policies->getCatchupWindow()->getSeconds());
        $this->assertFalse($policies->getPauseOnFailure());
        $this->assertEquals(ScheduleOverlapPolicy::SCHEDULE_OVERLAP_POLICY_UNSPECIFIED, $policies->getOverlapPolicy());

        // Test State
        $this->assertFalse($state->getLimitedActions());
        $this->assertSame(0, $state->getRemainingActions());
        $this->assertFalse($state->getPaused());
        $this->assertEmpty($state->getNotes());
    }

    protected function setUp(): void
    {
        $this->dataConverter = DataConverter::createDefault();
        parent::setUp();
    }

    private function createMapper(): ScheduleMapper
    {
        return new ScheduleMapper(
            $this->dataConverter,
            new Marshaller(new AttributeMapperFactory(new AttributeReader())),
        );
    }
}
