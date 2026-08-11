<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use Google\Protobuf\Duration;
use PHPUnit\Framework\AssertionFailedError;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\ActivityTaskScheduledEventAttributes;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\SignalExternalWorkflowExecutionInitiatedEventAttributes;
use Temporal\Api\History\V1\StartChildWorkflowExecutionInitiatedEventAttributes;
use Temporal\Api\History\V1\TimerStartedEventAttributes;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Testing\Interactions\WorkflowInteractions;
use Temporal\Tests\TestCase;

final class WorkflowInteractionsTestCase extends TestCase
{
    private DataConverterInterface $converter;

    protected function setUp(): void
    {
        $this->converter = DataConverter::createDefault();
        parent::setUp();
    }

    public function testActivityCallCount(): void
    {
        $interactions = WorkflowInteractions::fromEvents([
            $this->activityEvent('Pay', [100]),
            $this->activityEvent('Pay', [200]),
            $this->activityEvent('Refund', [50]),
        ], $this->converter);

        $interactions->activity('Pay')->assertCalledTimes(2);
        $interactions->activity('Refund')->assertCalledOnce();
        $interactions->activity('Unknown')->assertNeverCalled();
    }

    public function testActivityCalledOnceFailsWhenCalledTwice(): void
    {
        $interactions = WorkflowInteractions::fromEvents([
            $this->activityEvent('Pay', [1]),
            $this->activityEvent('Pay', [2]),
        ], $this->converter);

        $this->expectException(AssertionFailedError::class);
        $interactions->activity('Pay')->assertCalledOnce();
    }

    public function testWithInputMatchesRealInput(): void
    {
        $interactions = WorkflowInteractions::fromEvents([
            $this->activityEvent('Pay', [100]),
        ], $this->converter);

        $interactions->activity('Pay')->withInput(100)->assertCalledOnce();
        $interactions->activity('Pay')->withInput(999)->assertNeverCalled();
    }

    public function testWithInputWrongValueFailsAssertCalledOnce(): void
    {
        $interactions = WorkflowInteractions::fromEvents([
            $this->activityEvent('Pay', [100]),
        ], $this->converter);

        $this->expectException(AssertionFailedError::class);
        $interactions->activity('Pay')->withInput(101)->assertCalledOnce();
    }

    public function testChildWorkflowStart(): void
    {
        $interactions = WorkflowInteractions::fromEvents([
            $this->childEvent('SubWorkflow', ['x']),
        ], $this->converter);

        $interactions->childWorkflow('SubWorkflow')->assertStartedOnce();
        $interactions->childWorkflow('Other')->assertNeverStarted();
    }

    public function testTimerDurationDecodedToMs(): void
    {
        $interactions = WorkflowInteractions::fromEvents([
            $this->timerEvent(1800),
        ], $this->converter);

        $interactions->timer()->assertStarted('30 minutes');
        $interactions->timer()->assertStarted(1_800_000);
        $interactions->timer()->assertStartedTimes(1);
    }

    public function testExternalSignalSent(): void
    {
        $interactions = WorkflowInteractions::fromEvents([
            $this->signalEvent('cancelOrder'),
        ], $this->converter);

        $interactions->signal('cancelOrder')->assertSentOnce();
        $interactions->signal('other')->assertNeverSent();
    }

    public function testAssertNoOtherActivitiesFailsForUnqueriedActivity(): void
    {
        $interactions = WorkflowInteractions::fromEvents([
            $this->activityEvent('Pay', [1]),
            $this->activityEvent('Refund', [1]),
        ], $this->converter);

        $interactions->activity('Pay')->assertCalledOnce();

        $this->expectException(AssertionFailedError::class);
        $interactions->assertNoOtherActivities();
    }

    /**
     * @param list<mixed> $args
     */
    private function activityEvent(string $type, array $args): HistoryEvent
    {
        $attributes = (new ActivityTaskScheduledEventAttributes())
            ->setActivityType((new ActivityType())->setName($type))
            ->setInput($this->payloads($args));

        return (new HistoryEvent())
            ->setEventType(EventType::EVENT_TYPE_ACTIVITY_TASK_SCHEDULED)
            ->setActivityTaskScheduledEventAttributes($attributes);
    }

    /**
     * @param list<mixed> $args
     */
    private function childEvent(string $type, array $args): HistoryEvent
    {
        $attributes = (new StartChildWorkflowExecutionInitiatedEventAttributes())
            ->setWorkflowType((new WorkflowType())->setName($type))
            ->setInput($this->payloads($args));

        return (new HistoryEvent())
            ->setEventType(EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED)
            ->setStartChildWorkflowExecutionInitiatedEventAttributes($attributes);
    }

    private function timerEvent(int $seconds): HistoryEvent
    {
        $attributes = (new TimerStartedEventAttributes())
            ->setStartToFireTimeout((new Duration())->setSeconds($seconds));

        return (new HistoryEvent())
            ->setEventType(EventType::EVENT_TYPE_TIMER_STARTED)
            ->setTimerStartedEventAttributes($attributes);
    }

    private function signalEvent(string $name): HistoryEvent
    {
        $attributes = (new SignalExternalWorkflowExecutionInitiatedEventAttributes())
            ->setSignalName($name)
            ->setInput($this->payloads([]));

        return (new HistoryEvent())
            ->setEventType(EventType::EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED)
            ->setSignalExternalWorkflowExecutionInitiatedEventAttributes($attributes);
    }

    /**
     * @param list<mixed> $args
     */
    private function payloads(array $args): Payloads
    {
        return EncodedValues::fromValues($args, $this->converter)->toPayloads();
    }
}
