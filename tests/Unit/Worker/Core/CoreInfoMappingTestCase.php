<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use Coresdk\Activity_task\Start;
use Coresdk\Common\NamespacedWorkflowExecution;
use Coresdk\Workflow_activation\InitializeWorkflow;
use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
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
use Temporal\Internal\Workflow\Input;
use Temporal\Worker\Core\ActivityTasks;
use Temporal\Worker\Core\WorkflowActivations;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Workflow\WorkflowInfo;

final class CoreInfoMappingTestCase extends TestCase
{
    private DataConverterInterface $converter;
    private Marshaller $marshaller;

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

    #[\PHPUnit\Framework\Attributes\DataProvider('provideStarts')]
    public function testActivityInfoEqualsMarshalledRoadRunnerShape(Start $start): void
    {
        $token = "token\x00bytes";
        $request = (fn(): ServerRequest => $this->request($token, $start, 'queue'))->call(new ActivityTasks($this->converter));

        $expected = $this->marshaller->unmarshal($this->activityInfoArray($token, $start, 'queue'), new ActivityInfo());

        self::assertEquals($expected, $request->getOptions()['info']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideInits')]
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
    }

    private function assertWorkflowInfo(InitializeWorkflow $init): void
    {
        $activations = new WorkflowActivations($this->converter, static fn(): array => [], 'ns', 'queue', []);
        $request = (fn(): ServerRequest => $this->startWorkflow($init, 'run-id', new \Temporal\Worker\Transport\Command\Server\TickInfo(new \DateTimeImmutable())))->call($activations);
        $actual = $request->getOptions()['info'];

        $expected = $this->workflowInfoFromRoadRunnerShape($init, 'run-id');

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

    private function workflowInfoFromRoadRunnerShape(InitializeWorkflow $init, string $runId): WorkflowInfo
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

        $info = [
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
            'OriginalRunID' => $runId,
            'ParentWorkflowNamespace' => $init->getParentWorkflowInfo()?->getNamespace() ?? '',
            'ParentWorkflowExecution' => $execution($init->getParentWorkflowInfo()?->getWorkflowId(), $init->getParentWorkflowInfo()?->getRunId()),
            'RootWorkflowExecution' => $execution($init->getRootWorkflow()?->getWorkflowId(), $init->getRootWorkflow()?->getRunId()),
            'SearchAttributes' => $init->hasSearchAttributes() ? $this->jsonCollection(new SearchAttributes(), $init->getSearchAttributes()->serializeToJsonString(), 'getIndexedFields') : null,
            'Memo' => $init->hasMemo() ? $this->jsonCollection(new Memo(), $init->getMemo()->serializeToJsonString(), 'getFields') : null,
            'RetryPolicy' => $this->retryArray($init->getRetryPolicy()),
            'Priority' => $this->priorityArray($init->getPriority()),
            'TypedSearchAttributes' => $init->hasSearchAttributes() ? TypedSearchAttributes::fromJsonArray($typed) : TypedSearchAttributes::empty(),
        ];

        return $this->marshaller->unmarshal(['info' => $info], new Input())->info;
    }

    private function jsonCollection(SearchAttributes|Memo $message, string $json, string $getter): array
    {
        $message->mergeFromJsonString($json, true);

        return EncodedCollection::fromPayloadCollection($message->$getter(), $this->converter)->getValues();
    }

    private function activityInfoArray(string $token, Start $start, string $taskQueue): array
    {
        $startedTime = \DateTimeImmutable::createFromInterface($start->getStartedTime()->toDateTime());
        $timeout = $this->nanos($start->getStartToCloseTimeout()) ?: $this->nanos($start->getScheduleToCloseTimeout());

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
            'ScheduledTime' => \DateTimeImmutable::createFromInterface($start->getScheduledTime()->toDateTime())->format(\DATE_RFC3339_EXTENDED),
            'StartedTime' => $startedTime->format(\DATE_RFC3339_EXTENDED),
            'Deadline' => $startedTime->modify(\sprintf('+%d microseconds', \intdiv($timeout, 1000)))->format(\DATE_RFC3339_EXTENDED),
            'Attempt' => $start->getAttempt(),
            'RetryPolicy' => $this->retryArray($start->getRetryPolicy()),
            'Priority' => $this->priorityArray($start->getPriority()),
        ];
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
        return $duration === null ? 0 : $duration->getSeconds() * 1_000_000_000 + $duration->getNanos();
    }
}
