<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use Coresdk\Activity_task\Start;
use Coresdk\Common\NamespacedWorkflowExecution;
use Coresdk\Workflow_activation\InitializeWorkflow;
use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spiral\Attributes\AttributeReader;
use Temporal\Activity\ActivityInfo;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Priority;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Common\TypedSearchAttributes;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Workflow\Input;
use Temporal\Worker\Core\InfoFactory;
use Temporal\Worker\Core\PayloadMapper;
use Temporal\Workflow\WorkflowInfo;

final class CoreInfoMappingTestCase extends TestCase
{
    private const NANOS_PER_SECOND = 1_000_000_000;
    private const WORKFLOW_INFO_TICK_FIELDS = ['HistoryLength', 'HistorySize', 'ShouldContinueAsNew', 'BinaryChecksum'];

    private DataConverterInterface $converter;
    private Marshaller $marshaller;
    private InfoFactory $info;

    public static function provideStarts(): iterable
    {
        yield 'full' => [new Start([
            'workflow_namespace' => 'wf-ns',
            'workflow_type' => 'WfType',
            'workflow_execution' => new WorkflowExecution(['workflow_id' => 'wf-id', 'run_id' => 'run-id']),
            'activity_id' => '7',
            'activity_type' => 'Act.echo',
            'scheduled_time' => new Timestamp(['seconds' => 1_790_000_000, 'nanos' => 123_456_789]),
            'started_time' => new Timestamp(['seconds' => 1_790_000_001, 'nanos' => 5_000_000]),
            'attempt' => 3,
            'schedule_to_close_timeout' => new Duration(['seconds' => 60]),
            'start_to_close_timeout' => new Duration(['seconds' => 30, 'nanos' => 500_000_000]),
            'heartbeat_timeout' => new Duration(['seconds' => 5, 'nanos' => 250]),
            'retry_policy' => new RetryPolicy([
                'initial_interval' => new Duration(['seconds' => 1]),
                'backoff_coefficient' => 2.5,
                'maximum_interval' => new Duration(['seconds' => 100]),
                'maximum_attempts' => 4,
                'non_retryable_error_types' => ['A', 'B'],
            ]),
            'priority' => new Priority(['priority_key' => 2, 'fairness_key' => 'tenant', 'fairness_weight' => 1.3]),
        ])];
        yield 'minimal' => [new Start([
            'workflow_type' => 'WfType',
            'activity_id' => '1',
            'activity_type' => 'Act.echo',
            'scheduled_time' => new Timestamp(['seconds' => 1_790_000_000]),
            'started_time' => new Timestamp(['seconds' => 1_790_000_000]),
            'attempt' => 1,
            'schedule_to_close_timeout' => new Duration(['seconds' => 10]),
        ])];
        yield 'retry started late, schedule-to-close binds' => [new Start([
            'workflow_type' => 'WfType',
            'activity_id' => '2',
            'activity_type' => 'Act.echo',
            'scheduled_time' => new Timestamp(['seconds' => 1_790_000_000, 'nanos' => 250_000]),
            'started_time' => new Timestamp(['seconds' => 1_790_000_050, 'nanos' => 999_999_999]),
            'attempt' => 4,
            'schedule_to_close_timeout' => new Duration(['seconds' => 60]),
            'start_to_close_timeout' => new Duration(['seconds' => 30]),
        ])];
        yield 'start-to-close only' => [new Start([
            'workflow_type' => 'WfType',
            'activity_id' => '3',
            'activity_type' => 'Act.echo',
            'scheduled_time' => new Timestamp(['seconds' => 1_790_000_000]),
            'started_time' => new Timestamp(['seconds' => 1_790_000_020, 'nanos' => 1_000]),
            'attempt' => 1,
            'start_to_close_timeout' => new Duration(['seconds' => 5]),
        ])];
    }

    public static function provideInits(): iterable
    {
        yield 'minimal' => [new InitializeWorkflow([
            'workflow_type' => 'WfType',
            'workflow_id' => 'wf-id',
            'attempt' => 1,
            'first_execution_run_id' => 'run-id',
        ])];
        yield 'full' => [new InitializeWorkflow([
            'workflow_type' => 'WfType',
            'workflow_id' => 'wf-id',
            'attempt' => 2,
            'first_execution_run_id' => 'first-run',
            'original_execution_run_id' => 'original-run',
            'continued_from_execution_run_id' => 'prev-run',
            'cron_schedule' => '* * * * *',
            'workflow_execution_timeout' => new Duration(['seconds' => 3600]),
            'workflow_run_timeout' => new Duration(['seconds' => 600, 'nanos' => 1_000]),
            'workflow_task_timeout' => new Duration(['seconds' => 10]),
            'parent_workflow_info' => new NamespacedWorkflowExecution(['namespace' => 'parent-ns', 'workflow_id' => 'parent', 'run_id' => 'parent-run']),
            'root_workflow' => new WorkflowExecution(['workflow_id' => 'root', 'run_id' => 'root-run']),
            'retry_policy' => new RetryPolicy(['backoff_coefficient' => 2.0, 'maximum_attempts' => 5]),
            'priority' => new Priority(['priority_key' => 1, 'fairness_weight' => 0.7]),
        ])];
    }

    #[DataProvider('provideStarts')]
    public function testActivityInfoEqualsMarshalledRoadRunnerShape(Start $start): void
    {
        $token = "token\x00bytes";
        $actual = $this->info->activityInfo($token, $start, 'queue');

        $expected = $this->marshaller->unmarshal($this->activityInfoArray($token, $start, 'queue'), new ActivityInfo());

        self::assertEquals($expected, $actual);
        self::assertSame($expected->deadline->format('Y-m-d\TH:i:s.uP'), $actual->deadline->format('Y-m-d\TH:i:s.uP'));
        self::assertSame($expected->startedTime->format('Y-m-d\TH:i:s.uP'), $actual->startedTime->format('Y-m-d\TH:i:s.uP'));
    }

    public function testActivityDeadlineIsScheduleToCloseWhenItEndsFirst(): void
    {
        [$start] = \iterator_to_array(self::provideStarts())['retry started late, schedule-to-close binds'];

        $info = $this->info->activityInfo('token', $start, 'queue');

        self::assertSame('2026-09-21T14:14:20.000250+00:00', $info->deadline->format('Y-m-d\TH:i:s.uP'));
    }

    public function testRoadRunnerActivityShapeCoversEveryActivityInfoField(): void
    {
        [$start] = \iterator_to_array(self::provideStarts())['full'];

        self::assertEqualsCanonicalizing(
            self::marshalledNames(ActivityInfo::class),
            \array_keys($this->activityInfoArray('token', $start, 'queue')),
        );
    }

    public function testRoadRunnerWorkflowShapeCoversEveryWorkflowInfoField(): void
    {
        [$init] = \iterator_to_array(self::provideInits())['full'];

        self::assertEqualsCanonicalizing(
            self::marshalledNames(WorkflowInfo::class),
            [...\array_keys($this->workflowInfoArray($init, 'run-id')), ...self::WORKFLOW_INFO_TICK_FIELDS],
        );
    }

    #[DataProvider('provideInits')]
    public function testWorkflowInfoEqualsMarshalledRoadRunnerShape(InitializeWorkflow $init): void
    {
        $this->assertWorkflowInfo($init);
    }

    public function testWorkflowInfoWithSearchAttributesAndMemo(): void
    {
        $keyword = $this->converter->toPayload('value');
        $keyword->getMetadata()['type'] = 'Keyword';
        $number = $this->converter->toPayload(42);
        $number->getMetadata()['type'] = 'Int';

        $this->assertWorkflowInfo(new InitializeWorkflow([
            'workflow_type' => 'WfType',
            'workflow_id' => 'wf-id',
            'attempt' => 1,
            'search_attributes' => new SearchAttributes(['indexed_fields' => [
                'Kw' => $keyword,
                'Num' => $number,
                'Untyped' => $this->converter->toPayload('raw'),
            ]]),
            'memo' => new Memo(['fields' => ['m' => $this->converter->toPayload(['a' => 1])]]),
        ]));
    }

    protected function setUp(): void
    {
        $this->converter = DataConverter::createDefault();
        $this->marshaller = new Marshaller(new AttributeMapperFactory(new AttributeReader()));
        $this->info = new InfoFactory(new PayloadMapper($this->converter));
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    private static function marshalledNames(string $class): array
    {
        $names = [];
        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            foreach ($property->getAttributes(Marshal::class) as $attribute) {
                $names[] = $attribute->newInstance()->name;
            }
        }

        return $names;
    }

    private function assertWorkflowInfo(InitializeWorkflow $init): void
    {
        $actual = $this->info->workflowInfo($init, 'run-id', 'ns', 'queue');

        $expected = $this->marshaller->unmarshal(['info' => $this->workflowInfoArray($init, 'run-id')], new Input())->info;

        self::assertSame(\iterator_to_array($this->typed($expected->typedSearchAttributes)), \iterator_to_array($this->typed($actual->typedSearchAttributes)));
        $expected->typedSearchAttributes = $actual->typedSearchAttributes;
        self::assertEquals($expected, $actual);
    }

    private function typed(TypedSearchAttributes $attributes): \Generator
    {
        foreach ($attributes as $key => $value) {
            yield $key->getName() => [$key->getType(), $value];
        }
    }

    private function workflowInfoArray(InitializeWorkflow $init, string $runId): array
    {
        $execution = static fn(?string $id, ?string $run): ?array => $id ? ['ID' => $id, 'RunID' => (string) $run] : null;
        $typed = [];
        if ($init->hasSearchAttributes()) {
            foreach ($init->getSearchAttributes()->getIndexedFields() as $name => $payload) {
                $type = $payload->getMetadata()['type'] ?? null;
                if ($type !== null) {
                    $typed[$name] = ['type' => $type, 'value' => $this->converter->fromPayload($payload, null)];
                }
            }
        }

        return [
            'WorkflowExecution' => ['ID' => $init->getWorkflowId(), 'RunID' => $runId],
            'WorkflowType' => ['Name' => $init->getWorkflowType()],
            'TaskQueueName' => 'queue',
            'WorkflowExecutionTimeout' => $this->nanos($init->getWorkflowExecutionTimeout()),
            'WorkflowRunTimeout' => $this->nanos($init->getWorkflowRunTimeout()),
            'WorkflowTaskTimeout' => $this->nanos($init->getWorkflowTaskTimeout()),
            'Namespace' => 'ns',
            'Attempt' => $init->getAttempt(),
            'CronSchedule' => $init->getCronSchedule() ?: null,
            'ContinuedExecutionRunID' => $init->getContinuedFromExecutionRunId(),
            'FirstRunID' => $init->getFirstExecutionRunId(),
            'OriginalRunID' => $init->getOriginalExecutionRunId() ?: $runId,
            'ParentWorkflowNamespace' => $init->getParentWorkflowInfo()?->getNamespace() ?? '',
            'ParentWorkflowExecution' => $execution($init->getParentWorkflowInfo()?->getWorkflowId(), $init->getParentWorkflowInfo()?->getRunId()),
            'RootWorkflowExecution' => $execution($init->getRootWorkflow()?->getWorkflowId(), $init->getRootWorkflow()?->getRunId()),
            'SearchAttributes' => $init->hasSearchAttributes() ? $this->jsonCollection(new SearchAttributes(), $init->getSearchAttributes()->serializeToJsonString(), 'getIndexedFields') : null,
            'Memo' => $init->hasMemo() ? $this->jsonCollection(new Memo(), $init->getMemo()->serializeToJsonString(), 'getFields') : null,
            'RetryPolicy' => $this->retryArray($init->getRetryPolicy()),
            'Priority' => $this->priorityArray($init->getPriority()),
            'TypedSearchAttributes' => $init->hasSearchAttributes() ? TypedSearchAttributes::fromJsonArray($typed) : TypedSearchAttributes::empty(),
        ];
    }

    private function jsonCollection(SearchAttributes|Memo $message, string $json, string $getter): array
    {
        $message->mergeFromJsonString($json, true);

        return EncodedCollection::fromPayloadCollection($message->$getter(), $this->converter)->getValues();
    }

    private function activityInfoArray(string $token, Start $start, string $taskQueue): array
    {
        $scheduled = $this->timestampNanos($start->getScheduledTime());
        $started = $this->timestampNanos($start->getStartedTime());
        $scheduleToClose = $this->nanos($start->getScheduleToCloseTimeout());
        $startToClose = $this->nanos($start->getStartToCloseTimeout());
        $deadlines = \array_filter([
            $scheduleToClose > 0 ? $scheduled + $scheduleToClose : null,
            $startToClose > 0 ? $started + $startToClose : null,
        ]);

        return [
            'TaskToken' => $token,
            'WorkflowType' => ['Name' => $start->getWorkflowType()],
            'WorkflowNamespace' => $start->getWorkflowNamespace(),
            'WorkflowExecution' => [
                'ID' => $start->getWorkflowExecution()?->getWorkflowId() ?? '',
                'RunID' => $start->getWorkflowExecution()?->getRunId() ?? '',
            ],
            'ActivityID' => $start->getActivityId(),
            'ActivityType' => ['Name' => $start->getActivityType()],
            'TaskQueue' => $taskQueue,
            'HeartbeatTimeout' => $this->nanos($start->getHeartbeatTimeout()),
            'ScheduledTime' => $this->rfc3339Nano($scheduled),
            'StartedTime' => $this->rfc3339Nano($started),
            'Deadline' => $this->rfc3339Nano($deadlines === [] ? $started : \min($deadlines)),
            'Attempt' => $start->getAttempt(),
            'RetryPolicy' => $this->retryArray($start->getRetryPolicy()),
            'Priority' => $this->priorityArray($start->getPriority()),
        ];
    }

    private function rfc3339Nano(int $nanos): string
    {
        $fraction = \rtrim(\sprintf('%09d', $nanos % self::NANOS_PER_SECOND), '0');

        return \gmdate('Y-m-d\TH:i:s', \intdiv($nanos, self::NANOS_PER_SECOND)) . ($fraction === '' ? '' : '.' . $fraction) . 'Z';
    }

    private function timestampNanos(Timestamp $timestamp): int
    {
        return $timestamp->getSeconds() * self::NANOS_PER_SECOND + $timestamp->getNanos();
    }

    private function retryArray(?RetryPolicy $retry): ?array
    {
        return $retry === null ? null : [
            'InitialInterval' => $this->nanos($retry->getInitialInterval()),
            'BackoffCoefficient' => $retry->getBackoffCoefficient(),
            'MaximumInterval' => $this->nanos($retry->getMaximumInterval()),
            'MaximumAttempts' => $retry->getMaximumAttempts(),
            'NonRetryableErrorTypes' => \iterator_to_array($retry->getNonRetryableErrorTypes()),
        ];
    }

    private function priorityArray(?Priority $priority): array
    {
        return [
            'PriorityKey' => $priority?->getPriorityKey() ?? 0,
            'FairnessKey' => $priority?->getFairnessKey() ?? '',
            'FairnessWeight' => (float) \sprintf('%.7g', $priority?->getFairnessWeight() ?? 0.0),
        ];
    }

    private function nanos(?Duration $duration): int
    {
        return $duration === null ? 0 : $duration->getSeconds() * self::NANOS_PER_SECOND + $duration->getNanos();
    }
}
