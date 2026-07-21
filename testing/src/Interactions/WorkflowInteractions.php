<?php

declare(strict_types=1);

namespace Temporal\Testing\Interactions;

use Google\Protobuf\Duration;
use PHPUnit\Framework\Assert;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\MarkerRecordedEventAttributes;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Workflow\WorkflowRunInterface;

final class WorkflowInteractions
{
    private const MARKER_LOCAL_ACTIVITY = 'LocalActivity';
    private const MARKER_DETAIL_DATA = 'data';
    private const MARKER_ACTIVITY_TYPE_KEY = 'ActivityType';

    /**
     * @param list<RecordedCall> $calls
     */
    private function __construct(
        private readonly array $calls,
        private readonly DataConverterInterface $converter,
        /** @var array<string, true> */
        private array $queriedActivityTypes = [],
    ) {}

    public static function of(
        WorkflowClient $client,
        WorkflowRunInterface $run,
        ?DataConverterInterface $converter = null,
    ): self {
        $converter ??= DataConverter::createDefault();
        $history = $client->getWorkflowHistory($run->getExecution());

        return self::fromEvents($history->getEvents(), $converter);
    }

    /**
     * @param iterable<HistoryEvent> $events
     */
    public static function fromEvents(iterable $events, ?DataConverterInterface $converter = null): self
    {
        $converter ??= DataConverter::createDefault();
        $calls = [];
        foreach ($events as $event) {
            $call = self::decode($event);
            if ($call !== null) {
                $calls[] = $call;
            }
        }

        return new self($calls, $converter);
    }

    public function activity(string $type): ActivityAssertion
    {
        $this->queriedActivityTypes[$type] = true;

        return new ActivityAssertion($type, $this->callsOf(RecordedCallKind::Activity, $type), $this->converter);
    }

    public function localActivity(string $type): LocalActivityAssertion
    {
        return new LocalActivityAssertion($type, $this->callsOf(RecordedCallKind::LocalActivity, $type));
    }

    public function childWorkflow(string $type): ChildWorkflowAssertion
    {
        return new ChildWorkflowAssertion($type, $this->callsOf(RecordedCallKind::ChildWorkflow, $type));
    }

    public function timer(): TimerAssertion
    {
        return new TimerAssertion($this->callsOf(RecordedCallKind::Timer, null));
    }

    public function signal(string $type): SignalAssertion
    {
        return new SignalAssertion($type, $this->callsOf(RecordedCallKind::Signal, $type));
    }

    public function assertNoOtherActivities(): void
    {
        $unexpected = [];
        foreach ($this->calls as $call) {
            if ($call->kind === RecordedCallKind::Activity && !isset($this->queriedActivityTypes[$call->name])) {
                $unexpected[] = $call->name;
            }
        }

        Assert::assertSame(
            [],
            $unexpected,
            \sprintf('Unexpected activities scheduled by the workflow: %s', \implode(', ', $unexpected)),
        );
    }

    private static function decode(HistoryEvent $event): ?RecordedCall
    {
        switch ($event->getEventType()) {
            case EventType::EVENT_TYPE_ACTIVITY_TASK_SCHEDULED:
                $attributes = $event->getActivityTaskScheduledEventAttributes();
                return new RecordedCall(
                    RecordedCallKind::Activity,
                    $attributes?->getActivityType()?->getName() ?? '',
                    $attributes?->getInput(),
                    null,
                );
            case EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED:
                $attributes = $event->getStartChildWorkflowExecutionInitiatedEventAttributes();
                return new RecordedCall(
                    RecordedCallKind::ChildWorkflow,
                    $attributes?->getWorkflowType()?->getName() ?? '',
                    $attributes?->getInput(),
                    null,
                );
            case EventType::EVENT_TYPE_TIMER_STARTED:
                $attributes = $event->getTimerStartedEventAttributes();
                return new RecordedCall(
                    RecordedCallKind::Timer,
                    '',
                    null,
                    self::durationToMs($attributes?->getStartToFireTimeout()),
                );
            case EventType::EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED:
                $attributes = $event->getSignalExternalWorkflowExecutionInitiatedEventAttributes();
                return new RecordedCall(
                    RecordedCallKind::Signal,
                    $attributes?->getSignalName() ?? '',
                    $attributes?->getInput(),
                    null,
                );
            case EventType::EVENT_TYPE_MARKER_RECORDED:
                $attributes = $event->getMarkerRecordedEventAttributes();
                if ($attributes === null || $attributes->getMarkerName() !== self::MARKER_LOCAL_ACTIVITY) {
                    return null;
                }
                return new RecordedCall(
                    RecordedCallKind::LocalActivity,
                    self::localActivityType($attributes),
                    null,
                    null,
                );
            default:
                return null;
        }
    }

    private static function localActivityType(MarkerRecordedEventAttributes $attributes): string
    {
        foreach ($attributes->getDetails() as $key => $payloads) {
            if ($key !== self::MARKER_DETAIL_DATA) {
                continue;
            }

            $items = $payloads->getPayloads();
            if (\count($items) === 0) {
                return '';
            }

            $decoded = \json_decode($items[0]->getData(), true);

            return \is_array($decoded) ? (string) ($decoded[self::MARKER_ACTIVITY_TYPE_KEY] ?? '') : '';
        }

        return '';
    }

    private static function durationToMs(?Duration $duration): int
    {
        if ($duration === null) {
            return 0;
        }

        return (int) $duration->getSeconds() * 1000 + \intdiv($duration->getNanos(), 1_000_000);
    }

    /**
     * @return list<RecordedCall>
     */
    private function callsOf(RecordedCallKind $kind, ?string $name): array
    {
        $result = [];
        foreach ($this->calls as $call) {
            if ($call->kind === $kind && ($name === null || $call->name === $name)) {
                $result[] = $call;
            }
        }

        return $result;
    }
}
