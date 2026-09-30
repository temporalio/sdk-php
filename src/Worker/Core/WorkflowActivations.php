<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Carbon\CarbonInterval;
use Coresdk\Activity_result\ActivityResolution;
use Coresdk\Activity_result\DoBackoff;
use Coresdk\Child_workflow\ChildWorkflowCancellationType;
use Coresdk\Child_workflow\ChildWorkflowResult;
use Coresdk\Common\NamespacedWorkflowExecution;
use Coresdk\Workflow_activation\DoUpdate;
use Coresdk\Workflow_activation\InitializeWorkflow;
use Coresdk\Workflow_activation\QueryWorkflow;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStart;
use Coresdk\Workflow_activation\WorkflowActivation;
use Coresdk\Workflow_commands\ActivityCancellationType;
use Coresdk\Workflow_commands\CancelChildWorkflowExecution;
use Coresdk\Workflow_commands\CancelTimer;
use Coresdk\Workflow_commands\CancelWorkflowExecution;
use Coresdk\Workflow_commands\CompleteWorkflowExecution;
use Coresdk\Workflow_commands\ContinueAsNewWorkflowExecution;
use Coresdk\Workflow_commands\FailWorkflowExecution;
use Coresdk\Workflow_commands\ModifyWorkflowProperties;
use Coresdk\Workflow_commands\QueryResult;
use Coresdk\Workflow_commands\QuerySuccess;
use Coresdk\Workflow_commands\RequestCancelActivity;
use Coresdk\Workflow_commands\RequestCancelExternalWorkflowExecution;
use Coresdk\Workflow_commands\RequestCancelLocalActivity;
use Coresdk\Workflow_commands\ScheduleActivity;
use Coresdk\Workflow_commands\ScheduleLocalActivity;
use Coresdk\Workflow_commands\SetPatchMarker;
use Coresdk\Workflow_commands\SignalExternalWorkflowExecution;
use Coresdk\Workflow_commands\StartChildWorkflowExecution;
use Coresdk\Workflow_commands\StartTimer;
use Coresdk\Workflow_commands\UpdateResponse as CoreUpdateResponse;
use Coresdk\Workflow_commands\UpsertWorkflowSearchAttributes;
use Coresdk\Workflow_commands\WorkflowCommand;
use Coresdk\Workflow_completion\Failure as CompletionFailure;
use Coresdk\Workflow_completion\Success as CompletionSuccess;
use Coresdk\Workflow_completion\WorkflowActivationCompletion;
use Google\Protobuf\Duration;
use Google\Protobuf\GPBEmpty;
use Google\Protobuf\Internal\MapField;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Timestamp;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\Priority;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Sdk\V1\UserMetadata;
use Temporal\Common\Priority as PriorityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Common\TypedSearchAttributes;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Interceptor\Header;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Transport\Request\UndefinedResponse;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\Client\UpdateResponse;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Workflow\WorkflowExecution as WorkflowExecutionDto;
use Temporal\Workflow\WorkflowInfo;
use Temporal\Workflow\WorkflowType;

final class WorkflowActivations
{
    private const DEFAULT_VERSION = -1;
    private const SIDE_EFFECT_TIMEOUT_SECONDS = 60;
    private const SEARCH_ATTRIBUTE_TYPES = [
        'bool' => 'Bool',
        'float64' => 'Double',
        'int64' => 'Int',
        'keyword' => 'Keyword',
        'keyword_list' => 'KeywordList',
        'string' => 'Text',
        'datetime' => 'Datetime',
    ];

    /** @var array<string, RunState> */
    private array $runs = [];

    private readonly \DateTimeZone $timeZone;

    /**
     * @param \Closure(list<CommandInterface>, array): list<CommandInterface> $dispatch
     * @param array<string, int> $versioningBehaviors
     */
    public function __construct(
        private readonly DataConverterInterface $converter,
        private readonly \Closure $dispatch,
        private readonly string $namespace,
        private readonly string $taskQueue,
        private readonly array $versioningBehaviors,
        private readonly bool $failWorkflowOnPanic = false,
    ) {
        $this->timeZone = new \DateTimeZone(\date_default_timezone_get());
    }

    /**
     * @psalm-suppress InaccessibleProperty, PropertyTypeCoercion
     */
    public static function retryOptions(?RetryPolicy $retry): ?RetryOptions
    {
        if ($retry === null) {
            return null;
        }

        $options = (new \ReflectionClass(RetryOptions::class))->newInstanceWithoutConstructor();
        $options->initialInterval = self::interval($retry->getInitialInterval());
        $options->backoffCoefficient = $retry->getBackoffCoefficient();
        $options->maximumInterval = self::interval($retry->getMaximumInterval());
        $options->maximumAttempts = $retry->getMaximumAttempts();
        $options->nonRetryableExceptions = \iterator_to_array($retry->getNonRetryableErrorTypes());

        return $options;
    }

    public static function priorityOptions(?Priority $priority): PriorityOptions
    {
        $options = PriorityOptions::new($priority?->getPriorityKey() ?? 0);
        $options->fairnessKey = $priority?->getFairnessKey() ?? '';
        $options->fairnessWeight = (float) \sprintf('%.7g', $priority?->getFairnessWeight() ?? 0.0);

        return $options;
    }

    public function handle(string $bytes): string
    {
        $activation = new WorkflowActivation();
        $activation->mergeFromString($bytes);
        $completion = new WorkflowActivationCompletion(['run_id' => $activation->getRunId()]);

        try {
            $commands = $this->process($activation);
            $completion->setSuccessful(new CompletionSuccess([
                'commands' => $commands,
                'versioning_behavior' => $this->runs[$activation->getRunId()]->versioningBehavior ?? 0,
            ]));
        } catch (\Throwable $e) {
            $completion->setFailed(new CompletionFailure([
                'failure' => Failures::fromThrowable($e, $this->converter),
            ]));
        }

        return $completion->serializeToString();
    }

    private static function nanos(?Duration $duration): int
    {
        return $duration === null ? 0 : $duration->getSeconds() * 1_000_000_000 + $duration->getNanos();
    }

    private static function interval(?Duration $duration): CarbonInterval
    {
        return DateInterval::parse(self::nanos($duration), DateInterval::FORMAT_NANOSECONDS);
    }

    /**
     * @return list<WorkflowCommand>
     */
    private function process(WorkflowActivation $activation): array
    {
        $runId = $activation->getRunId();
        $tick = new TickInfo(
            time: $this->tickTime($activation->getTimestamp()),
            historyLength: $activation->getHistoryLength(),
            historySize: (int) $activation->getHistorySizeBytes(),
            continueAsNewSuggested: $activation->getContinueAsNewSuggested(),
            isReplaying: $activation->getIsReplaying(),
        );
        $run = $this->runs[$runId] ??= new RunState($runId);

        $messages = [];
        $queries = [];
        $commands = [];
        foreach ($activation->getJobs() as $job) {
            switch ($job->getVariant()) {
                case 'initialize_workflow':
                    $run->versioningBehavior = $this->versioningBehaviors[$job->getInitializeWorkflow()->getWorkflowType()] ?? 0;
                    $messages[] = $this->startWorkflow($job->getInitializeWorkflow(), $runId, $tick);
                    break;
                case 'fire_timer':
                    $seq = $job->getFireTimer()->getSeq();
                    if (isset($run->localActivityBackoffs[$seq])) {
                        $commands[] = $this->retryLocalActivity($run, $seq);
                        break;
                    }
                    $messages[] = new SuccessResponse(null, $run->release($seq), $tick);
                    break;
                case 'resolve_activity':
                    $resolve = $job->getResolveActivity();
                    if ($resolve->getResult()->getStatus() === 'backoff') {
                        $commands[] = $this->backoffLocalActivity($run, $resolve->getSeq(), $resolve->getResult()->getBackoff());
                        break;
                    }
                    $messages[] = $this->activityResolution($run, $resolve->getSeq(), $resolve->getResult(), $tick);
                    break;
                case 'signal_workflow':
                    $signal = $job->getSignalWorkflow();
                    $messages[] = new ServerRequest(
                        name: 'InvokeSignal',
                        info: $tick,
                        options: ['runId' => $runId, 'name' => $signal->getSignalName()],
                        payloads: $this->values($signal->getInput()),
                        id: $runId,
                        header: $this->header($signal->getHeaders()),
                    );
                    break;
                case 'query_workflow':
                    $queries[] = $job->getQueryWorkflow();
                    break;
                case 'cancel_workflow':
                    $messages[] = new ServerRequest('CancelWorkflow', $tick, ['runId' => $runId], id: $runId);
                    break;
                case 'do_update':
                    $messages[] = $this->update($run, $job->getDoUpdate(), $tick);
                    break;
                case 'notify_has_patch':
                    $patchId = $job->getNotifyHasPatch()->getPatchId();
                    $separator = \strrpos($patchId, '-');
                    if ($separator !== false) {
                        $run->patches[\substr($patchId, 0, $separator)] = (int) \substr($patchId, $separator + 1);
                    }
                    break;
                case 'resolve_child_workflow_execution_start':
                    \array_push($messages, ...$this->childStarted($run, $job->getResolveChildWorkflowExecutionStart(), $tick));
                    break;
                case 'resolve_child_workflow_execution':
                    $resolve = $job->getResolveChildWorkflowExecution();
                    $messages[] = $this->childResult($run->release($resolve->getSeq()), $resolve->getResult(), $tick);
                    break;
                case 'resolve_signal_external_workflow':
                    $resolve = $job->getResolveSignalExternalWorkflow();
                    $messages[] = $this->externalResult($run->release($resolve->getSeq()), $resolve->getFailure(), $tick);
                    break;
                case 'resolve_request_cancel_external_workflow':
                    $resolve = $job->getResolveRequestCancelExternalWorkflow();
                    $messages[] = $this->externalResult($run->release($resolve->getSeq()), $resolve->getFailure(), $tick);
                    break;
                case 'remove_from_cache':
                    unset($this->runs[$runId]);
                    ($this->dispatch)([new ServerRequest('DestroyWorkflow', $tick, ['runId' => $runId], id: $runId)], $this->headers());
                    return [];
                case 'update_random_seed':
                    break;
                default:
                    throw new \LogicException(\sprintf('Unsupported activation job "%s"', $job->getVariant()));
            }
        }

        $this->exchange($run, $messages, $tick, $commands);

        if ($queries !== []) {
            $queryTick = new TickInfo($tick->time, $tick->historyLength, $tick->historySize, $tick->continueAsNewSuggested);
            foreach ($queries as $query) {
                $commands[] = $this->query($run, $query, $queryTick);
            }
        }

        return $commands;
    }

    /**
     * @param list<CommandInterface> $messages
     * @param list<WorkflowCommand> $commands
     */
    private function exchange(RunState $run, array $messages, TickInfo $tick, array &$commands): void
    {
        while ($messages !== []) {
            $outgoing = ($this->dispatch)($messages, $this->headers());
            $messages = [];
            foreach ($outgoing as $command) {
                \array_push($messages, ...$this->translate($run, $command, $tick, $commands));
            }
        }
    }

    private function query(RunState $run, QueryWorkflow $query, TickInfo $tick): WorkflowCommand
    {
        $request = new ServerRequest(
            name: 'InvokeQuery',
            info: $tick,
            options: ['runId' => $run->runId, 'name' => $query->getQueryType()],
            payloads: $this->values($query->getArguments()),
            id: $run->runId,
            header: $this->header($query->getHeaders()),
        );

        $result = new QueryResult(['query_id' => $query->getQueryId()]);
        foreach (($this->dispatch)([$request], $this->headers()) as $response) {
            if ($response instanceof SuccessClientResponse) {
                $result->setSucceeded(new QuerySuccess(['response' => $this->firstPayload($response->getPayloads())]));
            } elseif ($response instanceof FailedClientResponse) {
                $result->setFailed(Failures::fromThrowable($response->getFailure(), $this->converter));
            }
        }

        if ($result->getVariant() === null || $result->getVariant() === '') {
            $result->setFailed(Failures::fromThrowable(new \LogicException('Query produced no result'), $this->converter));
        }

        return new WorkflowCommand(['respond_to_query' => $result]);
    }

    /**
     * @param list<WorkflowCommand> $commands
     * @return list<CommandInterface>
     */
    private function translate(RunState $run, CommandInterface $command, TickInfo $tick, array &$commands): array
    {
        if ($command instanceof SuccessClientResponse) {
            return [];
        }

        if ($command instanceof FailedClientResponse) {
            throw $command->getFailure();
        }

        if ($command instanceof UndefinedResponse) {
            throw new \LogicException($command->getOptions()['message'] ?? 'Undefined response');
        }

        if ($command instanceof UpdateResponse) {
            $commands[] = $this->updateResponse($run, $command);
            return [];
        }

        \assert($command instanceof RequestInterface);
        $options = $command->getOptions();
        $id = $command->getID();

        switch ($command->getName()) {
            case 'ExecuteActivity':
                $commands[] = new WorkflowCommand([
                    'schedule_activity' => $this->scheduleActivity($run, $run->bind($id, RunState::ACTIVITY), $command),
                    'user_metadata' => $this->userMetadata($options['options']['Summary'] ?? ''),
                ]);
                return [];

            case 'ExecuteLocalActivity':
                $commands[] = new WorkflowCommand([
                    'schedule_local_activity' => $this->scheduleLocalActivity($run, $run->bind($id, RunState::LOCAL_ACTIVITY), $command, $tick),
                    'user_metadata' => $this->userMetadata($options['options']['Summary'] ?? ''),
                ]);
                return [];

            case 'NewTimer':
                if (($options['ms'] ?? 0) <= 0) {
                    return [new SuccessResponse(null, $id, $tick)];
                }
                $commands[] = new WorkflowCommand([
                    'start_timer' => new StartTimer([
                        'seq' => $run->bind($id, RunState::TIMER),
                        'start_to_fire_timeout' => $this->duration($options['ms'] * 1_000_000),
                    ]),
                    'user_metadata' => $this->userMetadata($options['summary'] ?? ''),
                ]);
                return [];

            case 'CompleteWorkflow':
                $commands[] = $this->completeWorkflow($command);
                return [];

            case 'ContinueAsNew':
                $commands[] = new WorkflowCommand(['continue_as_new_workflow_execution' => $this->continueAsNew($command)]);
                return [];

            case 'Cancel':
                return [...$this->cancel($run, $options['ids'] ?? [], $tick, $commands), new SuccessResponse(null, $id, $tick)];

            case 'GetVersion':
                return [new SuccessResponse($this->getVersion($run, $options, $tick, $commands), $id, $tick)];

            case 'SideEffect':
                $seq = $run->bind($id, RunState::LOCAL_ACTIVITY);
                $commands[] = new WorkflowCommand([
                    'schedule_local_activity' => $run->localActivities[$seq] = new ScheduleLocalActivity([
                        'seq' => $seq,
                        'activity_id' => (string) $seq,
                        'activity_type' => ActivityTasks::SIDE_EFFECT,
                        'attempt' => 1,
                        'original_schedule_time' => $this->timestamp($tick->time),
                        'arguments' => $this->payloads($command->getPayloads()),
                        'schedule_to_close_timeout' => new Duration(['seconds' => self::SIDE_EFFECT_TIMEOUT_SECONDS]),
                    ]),
                    'user_metadata' => $this->userMetadata($options['summary'] ?? ''),
                ]);
                return [];

            case 'ExecuteChildWorkflow':
                $start = $this->startChild($run, $run->bind($id, RunState::CHILD), $command);
                $run->childWorkflowIds[$id] = $start->getWorkflowId();
                $commands[] = new WorkflowCommand([
                    'start_child_workflow_execution' => $start,
                    'user_metadata' => $this->userMetadata($options['options']['StaticSummary'] ?? '', $options['options']['StaticDetails'] ?? ''),
                ]);
                return [];

            case 'GetChildWorkflowExecution':
                $childId = (int) $options['id'];
                if (isset($run->childExecutions[$childId])) {
                    return [$this->childExecution($id, $run->childExecutions[$childId], $tick)];
                }
                $run->childWaiters[$childId][] = $id;
                return [];

            case 'SignalExternalWorkflow':
                $commands[] = new WorkflowCommand(['signal_external_workflow_execution' => $this->signalExternal($run->bind($id, RunState::SIGNAL_EXTERNAL), $command)]);
                return [];

            case 'CancelExternalWorkflow':
                $commands[] = new WorkflowCommand(['request_cancel_external_workflow_execution' => new RequestCancelExternalWorkflowExecution([
                    'seq' => $run->bind($id, RunState::CANCEL_EXTERNAL),
                    'workflow_execution' => new NamespacedWorkflowExecution([
                        'namespace' => $options['namespace'] ?: $this->namespace,
                        'workflow_id' => $options['workflowID'],
                        'run_id' => $options['runID'] ?? '',
                    ]),
                ])]);
                return [];

            case 'UpsertWorkflowSearchAttributes':
                $commands[] = new WorkflowCommand(['upsert_workflow_search_attributes' => new UpsertWorkflowSearchAttributes([
                    'search_attributes' => new SearchAttributes([
                        'indexed_fields' => EncodedCollection::fromValues((array) $options['searchAttributes'], $this->converter)->toPayloadArray(),
                    ]),
                ])]);
                return [];

            case 'UpsertWorkflowTypedSearchAttributes':
                $commands[] = new WorkflowCommand(['upsert_workflow_search_attributes' => new UpsertWorkflowSearchAttributes([
                    'search_attributes' => new SearchAttributes([
                        'indexed_fields' => $this->typedSearchAttributePayloads((array) $options['search_attributes']),
                    ]),
                ])]);
                return [];

            case 'UpsertMemo':
                $commands[] = new WorkflowCommand(['modify_workflow_properties' => new ModifyWorkflowProperties([
                    'upserted_memo' => new Memo([
                        'fields' => EncodedCollection::fromValues((array) $options['memo'], $this->converter)->toPayloadArray(),
                    ]),
                ])]);
                return [];

            case 'Panic':
                $panic = $command->getFailure() ?? new \RuntimeException($options['message'] ?? 'Workflow panic');
                if (!$this->failWorkflowOnPanic) {
                    throw $panic;
                }
                $commands[] = new WorkflowCommand(['fail_workflow_execution' => new FailWorkflowExecution([
                    'failure' => Failures::fromThrowable($panic, $this->converter),
                ])]);
                return [];

            default:
                throw new \LogicException(\sprintf('Command "%s" is not supported by the sdk-core transport', $command->getName()));
        }
    }

    /**
     * @param list<int> $ids
     * @param list<WorkflowCommand> $commands
     * @return list<CommandInterface>
     */
    private function cancel(RunState $run, array $ids, TickInfo $tick, array &$commands): array
    {
        $responses = [];
        foreach ($ids as $requestId) {
            $bound = $run->command($requestId);
            if ($bound === null) {
                continue;
            }

            [$type, $seq] = $bound;
            switch ($type) {
                case RunState::TIMER:
                    $run->release($seq);
                    unset($run->localActivityBackoffs[$seq]);
                    $commands[] = new WorkflowCommand(['cancel_timer' => new CancelTimer(['seq' => $seq])]);
                    $responses[] = new FailureResponse(new CanceledFailure('canceled'), $requestId, $tick);
                    break;
                case RunState::ACTIVITY:
                    $commands[] = new WorkflowCommand(['request_cancel_activity' => new RequestCancelActivity(['seq' => $seq])]);
                    break;
                case RunState::LOCAL_ACTIVITY:
                    $commands[] = new WorkflowCommand(['request_cancel_local_activity' => new RequestCancelLocalActivity(['seq' => $seq])]);
                    break;
                case RunState::CHILD:
                    $commands[] = new WorkflowCommand(['cancel_child_workflow_execution' => new CancelChildWorkflowExecution([
                        'child_workflow_seq' => $seq,
                    ])]);
                    break;
            }
        }

        return $responses;
    }

    /**
     * @param list<WorkflowCommand> $commands
     */
    private function getVersion(RunState $run, array $options, TickInfo $tick, array &$commands): ValuesInterface
    {
        $changeId = $options['changeID'];
        if (!isset($run->versions[$changeId])) {
            $version = $run->patches[$changeId] ?? ($tick->isReplaying ? self::DEFAULT_VERSION : (int) $options['maxSupported']);
            $run->versions[$changeId] = $version;
            if ($version !== self::DEFAULT_VERSION) {
                $commands[] = new WorkflowCommand(['set_patch_marker' => new SetPatchMarker(['patch_id' => $changeId . '-' . $version])]);
            }
        }

        $version = $run->versions[$changeId];
        if ($version < (int) $options['minSupported']) {
            throw new \LogicException(\sprintf(
                'Workflow code removed support of version %d for "%s" changeID. The oldest supported version is %d',
                $version,
                $changeId,
                $options['minSupported'],
            ));
        }
        if ($version > (int) $options['maxSupported']) {
            throw new \LogicException(\sprintf(
                'Workflow code is too old to support version %d for "%s" changeID. The maximum supported version is %d',
                $version,
                $changeId,
                $options['maxSupported'],
            ));
        }

        return EncodedValues::fromValues([$version], $this->converter);
    }

    private function completeWorkflow(RequestInterface $command): WorkflowCommand
    {
        $failure = $command->getFailure();
        if ($failure === null) {
            return new WorkflowCommand(['complete_workflow_execution' => new CompleteWorkflowExecution([
                'result' => $this->firstPayload($command->getPayloads()),
            ])]);
        }

        if ($failure instanceof CanceledFailure) {
            return new WorkflowCommand(['cancel_workflow_execution' => new CancelWorkflowExecution()]);
        }

        return new WorkflowCommand(['fail_workflow_execution' => new FailWorkflowExecution([
            'failure' => Failures::fromThrowable($failure, $this->converter),
        ])]);
    }

    private function scheduleActivity(RunState $run, int $seq, RequestInterface $command): ScheduleActivity
    {
        $options = $command->getOptions()['options'];
        $cancellationType = $this->cancellationType($options['WaitForCancellation'] ?? false);
        if ($cancellationType === ActivityCancellationType::TRY_CANCEL) {
            $run->tryCancelActivities[$seq] = true;
        }

        return new ScheduleActivity([
            'seq' => $seq,
            'activity_id' => (string) (($options['ActivityID'] ?? '') ?: $seq),
            'activity_type' => $command->getOptions()['name'],
            'task_queue' => $options['TaskQueueName'] ?? null ?: $this->taskQueue,
            'headers' => $this->headerFields($command),
            'arguments' => $this->payloads($command->getPayloads()),
            'schedule_to_close_timeout' => $this->optionalDuration($options['ScheduleToCloseTimeout'] ?? 0),
            'schedule_to_start_timeout' => $this->optionalDuration($options['ScheduleToStartTimeout'] ?? 0),
            'start_to_close_timeout' => $this->optionalDuration($options['StartToCloseTimeout'] ?? 0),
            'heartbeat_timeout' => $this->optionalDuration($options['HeartbeatTimeout'] ?? 0),
            'retry_policy' => $this->retryPolicy($options['RetryPolicy'] ?? null),
            'cancellation_type' => $cancellationType,
            'priority' => $this->priority($options['Priority'] ?? null),
        ]);
    }

    private function scheduleLocalActivity(RunState $run, int $seq, RequestInterface $command, TickInfo $tick): ScheduleLocalActivity
    {
        $options = $command->getOptions()['options'];

        return $run->localActivities[$seq] = new ScheduleLocalActivity([
            'seq' => $seq,
            'activity_id' => (string) $seq,
            'activity_type' => $command->getOptions()['name'],
            'attempt' => 1,
            'original_schedule_time' => $this->timestamp($tick->time),
            'headers' => $this->headerFields($command),
            'arguments' => $this->payloads($command->getPayloads()),
            'schedule_to_close_timeout' => $this->optionalDuration($options['ScheduleToCloseTimeout'] ?? 0),
            'start_to_close_timeout' => $this->optionalDuration($options['StartToCloseTimeout'] ?? 0),
            'retry_policy' => $this->retryPolicy($options['RetryPolicy'] ?? null),
        ]);
    }

    private function startChild(RunState $run, int $seq, RequestInterface $command): StartChildWorkflowExecution
    {
        $options = $command->getOptions()['options'];

        return new StartChildWorkflowExecution([
            'seq' => $seq,
            'namespace' => ($options['Namespace'] ?? '') ?: $this->namespace,
            'workflow_id' => ($options['WorkflowID'] ?? '') ?: $run->runId . '_' . $seq,
            'workflow_type' => $command->getOptions()['name'],
            'task_queue' => ($options['TaskQueueName'] ?? '') ?: $this->taskQueue,
            'input' => $this->payloads($command->getPayloads()),
            'workflow_execution_timeout' => $this->optionalDuration($options['WorkflowExecutionTimeout'] ?? 0),
            'workflow_run_timeout' => $this->optionalDuration($options['WorkflowRunTimeout'] ?? 0),
            'workflow_task_timeout' => $this->optionalDuration($options['WorkflowTaskTimeout'] ?? 0),
            'parent_close_policy' => (int) ($options['ParentClosePolicy'] ?? 0),
            'workflow_id_reuse_policy' => (int) ($options['WorkflowIDReusePolicy'] ?? 0),
            'retry_policy' => $this->retryPolicy($options['RetryPolicy'] ?? null),
            'cron_schedule' => (string) ($options['CronSchedule'] ?? ''),
            'headers' => $this->headerFields($command),
            'memo' => EncodedCollection::fromValues((array) ($options['Memo'] ?? []), $this->converter)->toPayloadArray(),
            'search_attributes' => isset($options['SearchAttributes'])
                ? new SearchAttributes(['indexed_fields' => EncodedCollection::fromValues((array) $options['SearchAttributes'], $this->converter)->toPayloadArray()])
                : null,
            'cancellation_type' => ChildWorkflowCancellationType::WAIT_CANCELLATION_COMPLETED,
            'priority' => $this->priority($options['Priority'] ?? null),
        ]);
    }

    private function signalExternal(int $seq, RequestInterface $command): SignalExternalWorkflowExecution
    {
        $options = $command->getOptions();
        $signal = new SignalExternalWorkflowExecution([
            'seq' => $seq,
            'signal_name' => $options['signal'],
            'args' => $this->payloads($command->getPayloads()),
            'headers' => $this->headerFields($command),
        ]);

        if ($options['childWorkflowOnly'] ?? false) {
            $signal->setChildWorkflowId($options['workflowID']);
        } else {
            $signal->setWorkflowExecution(new NamespacedWorkflowExecution([
                'namespace' => ($options['namespace'] ?? '') ?: $this->namespace,
                'workflow_id' => $options['workflowID'],
                'run_id' => $options['runID'] ?? '',
            ]));
        }

        return $signal;
    }

    private function continueAsNew(RequestInterface $command): ContinueAsNewWorkflowExecution
    {
        $options = $command->getOptions()['options'] ?? [];

        return new ContinueAsNewWorkflowExecution([
            'workflow_type' => $command->getOptions()['name'],
            'task_queue' => ($options['TaskQueueName'] ?? '') ?: $this->taskQueue,
            'arguments' => $this->payloads($command->getPayloads()),
            'workflow_run_timeout' => $this->optionalDuration($options['WorkflowRunTimeout'] ?? 0),
            'workflow_task_timeout' => $this->optionalDuration($options['WorkflowTaskTimeout'] ?? 0),
            'headers' => $this->headerFields($command),
        ]);
    }

    private function update(RunState $run, DoUpdate $update, TickInfo $tick): ServerRequest
    {
        $run->updates[$update->getId()] = $update->getProtocolInstanceId();

        return new ServerRequest(
            name: 'InvokeUpdate',
            info: $tick,
            options: [
                'runId' => $run->runId,
                'updateId' => $update->getId(),
                'name' => $update->getName(),
                'replay' => !$update->getRunValidator(),
            ],
            payloads: $this->values($update->getInput()),
            id: $run->runId,
            header: $this->header($update->getHeaders()),
        );
    }

    private function updateResponse(RunState $run, UpdateResponse $response): WorkflowCommand
    {
        $updateId = (string) $response->getOptions()['id'];
        $result = new CoreUpdateResponse(['protocol_instance_id' => $run->updates[$updateId] ?? $updateId]);
        $failure = $response->getFailure();

        match (true) {
            $failure !== null => $result->setRejected(Failures::fromThrowable($failure, $this->converter)),
            $response->getCommand() === UpdateResponse::COMMAND_VALIDATED => $result->setAccepted(new GPBEmpty()),
            default => $result->setCompleted($this->firstPayload($response->getPayloads())),
        };

        if ($response->getCommand() === UpdateResponse::COMMAND_COMPLETED || $failure !== null) {
            unset($run->updates[$updateId]);
        }

        return new WorkflowCommand(['update_response' => $result]);
    }

    /**
     * @return list<CommandInterface>
     */
    private function childStarted(RunState $run, ResolveChildWorkflowExecutionStart $start, TickInfo $tick): array
    {
        $childId = $run->requestId($start->getSeq());
        $messages = [];

        switch ($start->getStatus()) {
            case 'succeeded':
                $run->childExecutions[$childId] = [$run->childWorkflowIds[$childId], $start->getSucceeded()->getRunId()];
                break;
            case 'failed':
                $failed = $start->getFailed();
                $run->release($start->getSeq());
                $error = new ChildWorkflowFailure(
                    0,
                    0,
                    $failed->getWorkflowType(),
                    new WorkflowExecutionDto($failed->getWorkflowId()),
                    $this->namespace,
                    0,
                    new ApplicationFailure('Workflow execution already started', 'ChildWorkflowExecutionAlreadyStartedError', true),
                );
                $run->childExecutions[$childId] = $error;
                $messages[] = new FailureResponse($error, $childId, $tick);
                break;
            case 'cancelled':
                $run->release($start->getSeq());
                $error = FailureConverter::mapFailureToException($start->getCancelled()->getFailure(), $this->converter);
                $run->childExecutions[$childId] = $error;
                $messages[] = new FailureResponse($error, $childId, $tick);
                break;
        }

        foreach ($run->childWaiters[$childId] ?? [] as $waiter) {
            $messages[] = $this->childExecution($waiter, $run->childExecutions[$childId], $tick);
        }
        unset($run->childWaiters[$childId]);

        return $messages;
    }

    private function childExecution(int $requestId, array|\Throwable $execution, TickInfo $tick): CommandInterface
    {
        if ($execution instanceof \Throwable) {
            return new FailureResponse($execution, $requestId, $tick);
        }

        return new SuccessResponse(
            EncodedValues::fromValues([new WorkflowExecutionDto($execution[0], $execution[1])], $this->converter),
            $requestId,
            $tick,
        );
    }

    private function childResult(int $requestId, ChildWorkflowResult $result, TickInfo $tick): CommandInterface
    {
        return match ($result->getStatus()) {
            'completed' => new SuccessResponse($this->valuesFromPayload($result->getCompleted()->getResult()), $requestId, $tick),
            'failed' => new FailureResponse(FailureConverter::mapFailureToException($result->getFailed()->getFailure(), $this->converter), $requestId, $tick),
            default => new FailureResponse(FailureConverter::mapFailureToException($result->getCancelled()->getFailure(), $this->converter), $requestId, $tick),
        };
    }

    private function externalResult(int $requestId, ?Failure $failure, TickInfo $tick): CommandInterface
    {
        return $failure === null
            ? new SuccessResponse(null, $requestId, $tick)
            : new FailureResponse(FailureConverter::mapFailureToException($failure, $this->converter), $requestId, $tick);
    }

    private function activityResolution(RunState $run, int $seq, ActivityResolution $result, TickInfo $tick): CommandInterface
    {
        $requestId = $run->release($seq);
        $tryCancel = isset($run->tryCancelActivities[$seq]);
        unset($run->tryCancelActivities[$seq], $run->localActivities[$seq]);

        return match ($result->getStatus()) {
            'completed' => new SuccessResponse($this->valuesFromPayload($result->getCompleted()->getResult()), $requestId, $tick),
            'failed' => new FailureResponse(FailureConverter::mapFailureToException($result->getFailed()->getFailure(), $this->converter), $requestId, $tick),
            'cancelled' => new FailureResponse($this->cancelledActivity($result->getCancelled()->getFailure(), $tryCancel), $requestId, $tick),
        };
    }

    private function backoffLocalActivity(RunState $run, int $seq, DoBackoff $backoff): WorkflowCommand
    {
        $activity = $run->localActivities[$seq];
        unset($run->localActivities[$seq]);
        $activity->setAttempt($backoff->getAttempt());
        $activity->setOriginalScheduleTime($backoff->getOriginalScheduleTime());
        $timerSeq = $run->bind($run->release($seq), RunState::TIMER);
        $run->localActivityBackoffs[$timerSeq] = $activity;

        return new WorkflowCommand(['start_timer' => new StartTimer([
            'seq' => $timerSeq,
            'start_to_fire_timeout' => $backoff->getBackoffDuration(),
        ])]);
    }

    private function retryLocalActivity(RunState $run, int $timerSeq): WorkflowCommand
    {
        $activity = $run->localActivityBackoffs[$timerSeq];
        unset($run->localActivityBackoffs[$timerSeq]);
        $seq = $run->bind($run->release($timerSeq), RunState::LOCAL_ACTIVITY);
        $activity->setSeq($seq);
        $run->localActivities[$seq] = $activity;

        return new WorkflowCommand(['schedule_local_activity' => $activity]);
    }

    private function cancelledActivity(Failure $failure, bool $tryCancel): \Throwable
    {
        $error = FailureConverter::mapFailureToException($failure, $this->converter);

        return $tryCancel && $error->getPrevious() instanceof CanceledFailure ? $error->getPrevious() : $error;
    }

    private function startWorkflow(InitializeWorkflow $init, string $runId, TickInfo $tick): ServerRequest
    {
        $payloads = $this->values($init->getArguments());
        $options = ['info' => $this->workflowInfo($init, $runId)];

        $lastCompletion = $init->getLastCompletionResult()?->getPayloads();
        if ($lastCompletion !== null && \count($lastCompletion) > 0) {
            $all = new Payloads();
            $all->setPayloads([...$init->getArguments(), ...$lastCompletion]);
            $payloads = EncodedValues::fromPayloads($all, $this->converter);
            $options['lastCompletion'] = \count($lastCompletion);
        }

        return new ServerRequest(
            name: 'StartWorkflow',
            info: $tick,
            options: $options,
            payloads: $payloads,
            id: $runId,
            header: $this->header($init->getHeaders()),
        );
    }

    /**
     * @psalm-suppress InaccessibleProperty, ArgumentTypeCoercion, PropertyTypeCoercion
     */
    private function workflowInfo(InitializeWorkflow $init, string $runId): WorkflowInfo
    {
        $parent = $init->getParentWorkflowInfo();
        $root = $init->getRootWorkflow();

        $info = (new \ReflectionClass(WorkflowInfo::class))->newInstanceWithoutConstructor();
        $info->execution = new WorkflowExecutionDto($init->getWorkflowId(), $runId);
        $info->type = new WorkflowType();
        $info->type->name = $init->getWorkflowType();
        $info->taskQueue = $this->taskQueue;
        $info->executionTimeout = self::interval($init->getWorkflowExecutionTimeout());
        $info->runTimeout = self::interval($init->getWorkflowRunTimeout());
        $info->taskTimeout = self::interval($init->getWorkflowTaskTimeout());
        $info->namespace = $this->namespace;
        $info->attempt = $init->getAttempt();
        $info->cronSchedule = $init->getCronSchedule() ?: null;
        $info->continuedExecutionRunId = $init->getContinuedFromExecutionRunId();
        $info->firstExecutionRunId = $init->getFirstExecutionRunId();
        $info->originalExecutionRunId = $runId;
        $info->parentNamespace = $parent?->getNamespace() ?? '';
        $info->parentExecution = $this->execution($parent?->getWorkflowId(), $parent?->getRunId());
        $info->rootExecution = $this->execution($root?->getWorkflowId(), $root?->getRunId());
        $info->typedSearchAttributes = TypedSearchAttributes::empty();
        if ($init->hasSearchAttributes()) {
            $info->searchAttributes = EncodedCollection::fromPayloadCollection($init->getSearchAttributes()->getIndexedFields(), $this->converter)->getValues();
            $info->typedSearchAttributes = TypedSearchAttributes::fromJsonArray($this->typedSearchAttributes($init->getSearchAttributes()));
        }
        if ($init->hasMemo()) {
            $info->memo = EncodedCollection::fromPayloadCollection($init->getMemo()->getFields(), $this->converter)->getValues();
        }
        $info->retryOptions = self::retryOptions($init->getRetryPolicy());
        $info->priority = self::priorityOptions($init->getPriority());

        return $info;
    }

    private function tickTime(?Timestamp $timestamp): \DateTimeImmutable
    {
        $time = $timestamp === null ? new \DateTimeImmutable() : \DateTimeImmutable::createFromInterface($timestamp->toDateTime());

        return $time->setTimezone($this->timeZone);
    }

    private function typedSearchAttributes(SearchAttributes $attributes): array
    {
        $result = [];
        foreach ($attributes->getIndexedFields() as $name => $payload) {
            $type = $payload->getMetadata()['type'] ?? null;
            if ($type !== null) {
                $result[$name] = ['type' => $type, 'value' => $this->converter->fromPayload($payload, null)];
            }
        }

        return $result;
    }

    /**
     * @return array<string, Payload>
     */
    private function typedSearchAttributePayloads(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $name => $attribute) {
            if ($attribute['operation'] !== 'set') {
                $result[$name] = $this->converter->toPayload(null);
                continue;
            }

            $payload = $this->converter->toPayload($attribute['value']);
            $payload->getMetadata()['type'] = self::SEARCH_ATTRIBUTE_TYPES[$attribute['type']];
            $result[$name] = $payload;
        }

        return $result;
    }

    private function userMetadata(?string $summary, ?string $details = null): ?UserMetadata
    {
        if (!$summary && !$details) {
            return null;
        }

        return new UserMetadata([
            'summary' => $summary ? $this->converter->toPayload($summary) : null,
            'details' => $details ? $this->converter->toPayload($details) : null,
        ]);
    }

    private function priority(?array $priority): ?Priority
    {
        if ($priority === null) {
            return null;
        }

        return new Priority([
            'priority_key' => (int) ($priority['priority_key'] ?? 0),
            'fairness_key' => (string) ($priority['fairness_key'] ?? ''),
            'fairness_weight' => (float) ($priority['fairness_weight'] ?? 0),
        ]);
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    private function execution(?string $workflowId, ?string $runId): ?WorkflowExecutionDto
    {
        return $workflowId ? new WorkflowExecutionDto($workflowId, (string) $runId) : null;
    }

    private function headers(): array
    {
        return ['taskQueue' => $this->taskQueue];
    }

    private function values(RepeatedField $payloads): EncodedValues
    {
        return EncodedValues::fromPayloadCollection($payloads, $this->converter);
    }

    private function valuesFromPayload(?Payload $payload): ?EncodedValues
    {
        return $payload === null ? null : EncodedValues::fromPayloadCollection(new \ArrayIterator([$payload]), $this->converter);
    }

    private function header(MapField $fields): Header
    {
        return Header::fromPayloadCollection($fields, $this->converter);
    }

    private function headerFields(RequestInterface $command): array
    {
        $header = $command->getHeader();
        \assert($header instanceof Header);
        $header->setDataConverter($this->converter);

        return \iterator_to_array($header->toHeader()->getFields());
    }

    private function payloads(ValuesInterface $values): RepeatedField
    {
        $values->setDataConverter($this->converter);

        return $values->toPayloads()->getPayloads();
    }

    private function firstPayload(?ValuesInterface $values): ?Payload
    {
        if ($values === null) {
            return null;
        }

        $payloads = $this->payloads($values);

        return \count($payloads) > 0 ? $payloads[0] : null;
    }

    private function duration(int $nanos): Duration
    {
        return new Duration(['seconds' => \intdiv($nanos, 1_000_000_000), 'nanos' => $nanos % 1_000_000_000]);
    }

    private function optionalDuration(int|array|null $nanos): ?Duration
    {
        if (\is_array($nanos)) {
            return new Duration(['seconds' => (int) ($nanos['seconds'] ?? 0), 'nanos' => (int) ($nanos['nanos'] ?? 0)]);
        }

        return $nanos > 0 ? $this->duration($nanos) : null;
    }

    private function timestamp(\DateTimeInterface $time): Timestamp
    {
        $timestamp = new Timestamp();
        $timestamp->fromDateTime($time);

        return $timestamp;
    }

    private function retryPolicy(?array $retry): ?RetryPolicy
    {
        if ($retry === null) {
            return null;
        }

        return new RetryPolicy([
            'initial_interval' => $this->optionalDuration($retry['initial_interval'] ?? null),
            'backoff_coefficient' => (float) ($retry['backoff_coefficient'] ?? 0),
            'maximum_interval' => $this->optionalDuration($retry['maximum_interval'] ?? null),
            'maximum_attempts' => (int) ($retry['maximum_attempts'] ?? 0),
            'non_retryable_error_types' => $retry['non_retryable_error_types'] ?? [],
        ]);
    }

    private function cancellationType(mixed $waitForCancellation): int
    {
        return match (true) {
            $waitForCancellation === true => ActivityCancellationType::WAIT_CANCELLATION_COMPLETED,
            \is_int($waitForCancellation) => $waitForCancellation,
            default => ActivityCancellationType::TRY_CANCEL,
        };
    }
}
