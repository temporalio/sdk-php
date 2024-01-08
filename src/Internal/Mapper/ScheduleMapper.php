<?php

declare(strict_types=1);

namespace Temporal\Internal\Mapper;

use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Schedule\V1\CalendarSpec;
use Temporal\Api\Schedule\V1\IntervalSpec;
use Temporal\Api\Schedule\V1\Range;
use Temporal\Api\Schedule\V1\SchedulePolicies;
use Temporal\Api\Schedule\V1\ScheduleSpec;
use Temporal\Api\Schedule\V1\ScheduleState;
use Temporal\Api\Schedule\V1\StructuredCalendarSpec;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Action\ScheduleAction;
use Temporal\Client\Schedule\Schedule;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;

final class ScheduleMapper
{
    public function __construct(
        private readonly DataConverterInterface $converter,
        private readonly MarshallerInterface $marshaller,
    ) {
    }

    public function toMessage(Schedule $dto): \Temporal\Api\Schedule\V1\Schedule
    {
        if ($dto->action instanceof StartWorkflowAction) {
            $action = $dto->action;
            $action->input?->setDataConverter($this->converter);
            $action->header?->setDataConverter($this->converter);
            $action->memo?->setDataConverter($this->converter);
            $action->searchAttributes?->setDataConverter($this->converter);
        }

        $array = $this->marshaller->marshal($dto);
        $array['policies']['overlap_policy'] = $dto->policies->overlapPolicy->value;

        $array['policies'] = new SchedulePolicies($array['policies'] ?? []);
        $array['spec'] = $this->prepareSpec($array['spec'] ?? []);
        $array['state'] = new ScheduleState($array['state'] ?? []);
        isset($array['action']) and $array['action'] = $this->prepareAction($dto->action, $array['action']);

        return new \Temporal\Api\Schedule\V1\Schedule(self::cleanArray($array));
    }

    /**
     * @psalm-suppress TypeDoesNotContainNull,RedundantCondition
     */
    private function prepareAction(ScheduleAction $action, array $array): \Temporal\Api\Schedule\V1\ScheduleAction
    {
        $result = new \Temporal\Api\Schedule\V1\ScheduleAction();
        $values = \reset($array);
        switch (\array_key_first($array)) {
            case 'start_workflow':
                \assert($action instanceof StartWorkflowAction);
                $values['workflow_type'] = (new WorkflowType())
                    /** Because it is mapped with wrong key {@see \Temporal\Workflow\WorkflowType::$name} */
                    ->setName($values['workflow_type']['Name']);
                $values['task_queue'] = new TaskQueue($values['task_queue']);
                $action->input?->setDataConverter($this->converter);
                $values['input'] = $action->input?->toPayloads() ?? new Payloads();
                $values['workflow_id_reuse_policy'] = $action->workflowIdReusePolicy->value;
                $values['retry_policy'] = $action->retryPolicy?->toWorkflowRetryPolicy();

                $result->setStartWorkflow(
                    new \Temporal\Api\Workflow\V1\NewWorkflowExecutionInfo(self::cleanArray($values)),
                );
                break;
            default:
                throw new \InvalidArgumentException('Unknown action type');
        }

        return $result;
    }

    private function prepareSpec(array $result): ScheduleSpec
    {
        $result['structured_calendar'] = $this->prepareStructuredCalendar($result['structured_calendar'] ?? []);

        $result['calendar'] = \array_map(
            static fn(array $item): CalendarSpec => new CalendarSpec($item),
            $result['calendar'] ?? [],
        );

        $result['exclude_calendar'] = \array_map(
            static fn(array $item): CalendarSpec => new CalendarSpec($item),
            $result['exclude_calendar'] ?? [],
        );

        $result['interval'] = \array_map(
            static fn(array $item): IntervalSpec => new IntervalSpec($item),
            $result['interval'] ?? [],
        );

        $result['exclude_structured_calendar'] = $this->prepareStructuredCalendar(
            $result['exclude_structured_calendar'] ?? [],
        );

        return new ScheduleSpec(self::cleanArray($result));
    }

    private function prepareStructuredCalendar(array $array): array
    {
        foreach ($array as &$calendar) {
            // Convert Range fields
            foreach (['second', 'minute', 'hour', 'day_of_month', 'month', 'year', 'day_of_week'] as $key) {
                $calendar[$key] = \array_map(
                    static fn(array $item): Range => new Range($item),
                    $calendar[$key] ?? [],
                );
            }

            $calendar = new StructuredCalendarSpec(self::cleanArray($calendar));
        }

        return $array;
    }

    private static function cleanArray(array $array): array
    {
        return \array_filter($array, static fn ($item): bool => $item !== null);
    }
}
