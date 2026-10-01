<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Child_workflow\ChildWorkflowCancellationType;
use Coresdk\Common\NamespacedWorkflowExecution;
use Coresdk\Workflow_commands\ActivityCancellationType;
use Coresdk\Workflow_commands\CancelChildWorkflowExecution;
use Coresdk\Workflow_commands\CancelTimer;
use Coresdk\Workflow_commands\CancelWorkflowExecution;
use Coresdk\Workflow_commands\CompleteWorkflowExecution;
use Coresdk\Workflow_commands\ContinueAsNewWorkflowExecution;
use Coresdk\Workflow_commands\FailWorkflowExecution;
use Coresdk\Workflow_commands\ModifyWorkflowProperties;
use Coresdk\Workflow_commands\RequestCancelActivity;
use Coresdk\Workflow_commands\RequestCancelExternalWorkflowExecution;
use Coresdk\Workflow_commands\RequestCancelLocalActivity;
use Coresdk\Workflow_commands\ScheduleActivity;
use Coresdk\Workflow_commands\ScheduleLocalActivity;
use Coresdk\Workflow_commands\SignalExternalWorkflowExecution;
use Coresdk\Workflow_commands\StartChildWorkflowExecution;
use Coresdk\Workflow_commands\StartTimer;
use Coresdk\Workflow_commands\UpdateResponse as CoreUpdateResponse;
use Coresdk\Workflow_commands\UpsertWorkflowSearchAttributes;
use Coresdk\Workflow_commands\WorkflowCommand;
use Google\Protobuf\Duration;
use Google\Protobuf\GPBEmpty;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Priority;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Sdk\V1\UserMetadata;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Transport\Request;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\Client\UpdateResponse;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;

final class CommandTranslator
{
    private const NANOS_PER_MILLISECOND = 1_000_000;
    private const SIDE_EFFECT_TIMEOUT_SECONDS = 60;
    private const SEARCH_ATTRIBUTE_SET = 'set';
    private const SEARCH_ATTRIBUTE_TYPES = [
        'bool' => 'Bool',
        'float64' => 'Double',
        'int64' => 'Int',
        'keyword' => 'Keyword',
        'keyword_list' => 'KeywordList',
        'string' => 'Text',
        'datetime' => 'Datetime',
    ];

    public function __construct(
        private readonly PayloadMapper $payloads,
        private readonly ResolutionMapper $resolutions,
        private readonly string $namespace,
        private readonly string $taskQueue,
        private readonly bool $failWorkflowOnPanic,
    ) {}

    /**
     * @param list<WorkflowCommand> $commands
     * @return list<CommandInterface>
     */
    public function translate(RunState $run, CommandInterface $command, TickInfo $tick, array &$commands): array
    {
        if ($command instanceof SuccessClientResponse) {
            return [];
        }

        if ($command instanceof FailedClientResponse) {
            throw $command->getFailure();
        }

        if ($command instanceof Request\UndefinedResponse) {
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
            case Request\ExecuteActivity::NAME:
                $commands[] = new WorkflowCommand([
                    'schedule_activity' => $this->scheduleActivity($run, $run->bind($id, RunState::ACTIVITY), $command),
                    'user_metadata' => $this->userMetadata($options['options']['Summary'] ?? ''),
                ]);
                return [];

            case Request\ExecuteLocalActivity::NAME:
                $commands[] = new WorkflowCommand([
                    'schedule_local_activity' => $run->localActivities->schedule(
                        $this->scheduleLocalActivity($run->bind($id, RunState::LOCAL_ACTIVITY), $command, $tick),
                    ),
                    'user_metadata' => $this->userMetadata($options['options']['Summary'] ?? ''),
                ]);
                return [];

            case Request\NewTimer::NAME:
                $ms = (int) ($options['ms'] ?? 0);
                if ($ms <= 0) {
                    return [new SuccessResponse(null, $id, $tick)];
                }
                $commands[] = new WorkflowCommand([
                    'start_timer' => new StartTimer([
                        'seq' => $run->bind($id, RunState::TIMER),
                        'start_to_fire_timeout' => ProtoTime::duration($ms * self::NANOS_PER_MILLISECOND),
                    ]),
                    'user_metadata' => $this->userMetadata($options['summary'] ?? ''),
                ]);
                return [];

            case Request\CompleteWorkflow::NAME:
                $commands[] = $this->completeWorkflow($command);
                return [];

            case Request\ContinueAsNew::NAME:
                $commands[] = new WorkflowCommand(['continue_as_new_workflow_execution' => $this->continueAsNew($command)]);
                return [];

            case Request\Cancel::NAME:
                return [...$this->cancel($run, $options['ids'] ?? [], $tick, $commands), new SuccessResponse(null, $id, $tick)];

            case Request\GetVersion::NAME:
                $version = $run->patches->version(
                    $options['changeID'],
                    (int) $options['minSupported'],
                    (int) $options['maxSupported'],
                    $tick->isReplaying,
                    $commands,
                );
                return [new SuccessResponse($this->payloads->encode([$version]), $id, $tick)];

            case Request\SideEffect::NAME:
                $commands[] = new WorkflowCommand([
                    'schedule_local_activity' => $run->localActivities->schedule(
                        $this->sideEffect($run->bind($id, RunState::LOCAL_ACTIVITY), $command, $tick),
                    ),
                    'user_metadata' => $this->userMetadata($options['summary'] ?? ''),
                ]);
                return [];

            case Request\ExecuteChildWorkflow::NAME:
                $start = $this->startChild($run, $run->bind($id, RunState::CHILD), $command);
                $run->startChild($id, $start->getWorkflowId());
                $commands[] = new WorkflowCommand([
                    'start_child_workflow_execution' => $start,
                    'user_metadata' => $this->userMetadata($options['options']['StaticSummary'] ?? '', $options['options']['StaticDetails'] ?? ''),
                ]);
                return [];

            case Request\GetChildWorkflowExecution::NAME:
                $childId = (int) $options['id'];
                $execution = $run->childExecution($childId);
                if ($execution !== null) {
                    return [$this->resolutions->childExecution($id, $execution, $tick)];
                }
                $run->awaitChildExecution($childId, $id);
                return [];

            case Request\SignalExternalWorkflow::NAME:
                $commands[] = new WorkflowCommand(['signal_external_workflow_execution' => $this->signalExternal($run->bind($id, RunState::SIGNAL_EXTERNAL), $command)]);
                return [];

            case Request\CancelExternalWorkflow::NAME:
                $commands[] = new WorkflowCommand(['request_cancel_external_workflow_execution' => new RequestCancelExternalWorkflowExecution([
                    'seq' => $run->bind($id, RunState::CANCEL_EXTERNAL),
                    'workflow_execution' => new NamespacedWorkflowExecution([
                        'namespace' => $options['namespace'] ?: $this->namespace,
                        'workflow_id' => $options['workflowID'],
                        'run_id' => $options['runID'] ?? '',
                    ]),
                ])]);
                return [];

            case Request\UpsertSearchAttributes::NAME:
                $commands[] = new WorkflowCommand(['upsert_workflow_search_attributes' => new UpsertWorkflowSearchAttributes([
                    'search_attributes' => new SearchAttributes([
                        'indexed_fields' => $this->payloads->collection((array) $options['searchAttributes']),
                    ]),
                ])]);
                return [];

            case Request\UpsertTypedSearchAttributes::NAME:
                $commands[] = new WorkflowCommand(['upsert_workflow_search_attributes' => new UpsertWorkflowSearchAttributes([
                    'search_attributes' => new SearchAttributes([
                        'indexed_fields' => $this->typedSearchAttributePayloads((array) $options['search_attributes']),
                    ]),
                ])]);
                return [];

            case Request\UpsertMemo::NAME:
                $commands[] = new WorkflowCommand(['modify_workflow_properties' => new ModifyWorkflowProperties([
                    'upserted_memo' => new Memo([
                        'fields' => $this->payloads->collection((array) $options['memo']),
                    ]),
                ])]);
                return [];

            case Request\Panic::NAME:
                $panic = $command->getFailure() ?? new \RuntimeException($options['message'] ?? 'Workflow panic');
                if (!$this->failWorkflowOnPanic) {
                    throw $panic;
                }
                $commands[] = new WorkflowCommand(['fail_workflow_execution' => new FailWorkflowExecution([
                    'failure' => $this->payloads->failure($panic),
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
                    $run->localActivities->forget($seq);
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

    private function completeWorkflow(RequestInterface $command): WorkflowCommand
    {
        $failure = $command->getFailure();
        if ($failure === null) {
            return new WorkflowCommand(['complete_workflow_execution' => new CompleteWorkflowExecution([
                'result' => $this->payloads->firstPayload($command->getPayloads()),
            ])]);
        }

        if ($failure instanceof CanceledFailure) {
            return new WorkflowCommand(['cancel_workflow_execution' => new CancelWorkflowExecution()]);
        }

        return new WorkflowCommand(['fail_workflow_execution' => new FailWorkflowExecution([
            'failure' => $this->payloads->failure($failure),
        ])]);
    }

    private function scheduleActivity(RunState $run, int $seq, RequestInterface $command): ScheduleActivity
    {
        $options = $command->getOptions()['options'];
        $cancellationType = $this->cancellationType($options['WaitForCancellation'] ?? false);
        if ($cancellationType === ActivityCancellationType::TRY_CANCEL) {
            $run->markTryCancel($seq);
        }

        return new ScheduleActivity([
            'seq' => $seq,
            'activity_id' => (string) (($options['ActivityID'] ?? '') ?: $seq),
            'activity_type' => $command->getOptions()['name'],
            'task_queue' => $options['TaskQueueName'] ?? null ?: $this->taskQueue,
            'headers' => $this->payloads->headerFields($command),
            'arguments' => $this->payloads->payloads($command->getPayloads()),
            'schedule_to_close_timeout' => ProtoTime::optionalDuration($options['ScheduleToCloseTimeout'] ?? 0),
            'schedule_to_start_timeout' => ProtoTime::optionalDuration($options['ScheduleToStartTimeout'] ?? 0),
            'start_to_close_timeout' => ProtoTime::optionalDuration($options['StartToCloseTimeout'] ?? 0),
            'heartbeat_timeout' => ProtoTime::optionalDuration($options['HeartbeatTimeout'] ?? 0),
            'retry_policy' => $this->retryPolicy($options['RetryPolicy'] ?? null),
            'cancellation_type' => $cancellationType,
            'priority' => $this->priority($options['Priority'] ?? null),
        ]);
    }

    private function scheduleLocalActivity(int $seq, RequestInterface $command, TickInfo $tick): ScheduleLocalActivity
    {
        $options = $command->getOptions()['options'];

        return new ScheduleLocalActivity([
            'seq' => $seq,
            'activity_id' => (string) $seq,
            'activity_type' => $command->getOptions()['name'],
            'attempt' => 1,
            'original_schedule_time' => ProtoTime::timestamp($tick->time),
            'headers' => $this->payloads->headerFields($command),
            'arguments' => $this->payloads->payloads($command->getPayloads()),
            'schedule_to_close_timeout' => ProtoTime::optionalDuration($options['ScheduleToCloseTimeout'] ?? 0),
            'start_to_close_timeout' => ProtoTime::optionalDuration($options['StartToCloseTimeout'] ?? 0),
            'retry_policy' => $this->retryPolicy($options['RetryPolicy'] ?? null),
        ]);
    }

    private function sideEffect(int $seq, RequestInterface $command, TickInfo $tick): ScheduleLocalActivity
    {
        return new ScheduleLocalActivity([
            'seq' => $seq,
            'activity_id' => (string) $seq,
            'activity_type' => ActivityTasks::SIDE_EFFECT,
            'attempt' => 1,
            'original_schedule_time' => ProtoTime::timestamp($tick->time),
            'arguments' => $this->payloads->payloads($command->getPayloads()),
            'schedule_to_close_timeout' => new Duration(['seconds' => self::SIDE_EFFECT_TIMEOUT_SECONDS]),
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
            'input' => $this->payloads->payloads($command->getPayloads()),
            'workflow_execution_timeout' => ProtoTime::optionalDuration($options['WorkflowExecutionTimeout'] ?? 0),
            'workflow_run_timeout' => ProtoTime::optionalDuration($options['WorkflowRunTimeout'] ?? 0),
            'workflow_task_timeout' => ProtoTime::optionalDuration($options['WorkflowTaskTimeout'] ?? 0),
            'parent_close_policy' => (int) ($options['ParentClosePolicy'] ?? 0),
            'workflow_id_reuse_policy' => (int) ($options['WorkflowIDReusePolicy'] ?? 0),
            'retry_policy' => $this->retryPolicy($options['RetryPolicy'] ?? null),
            'cron_schedule' => (string) ($options['CronSchedule'] ?? ''),
            'headers' => $this->payloads->headerFields($command),
            'memo' => $this->payloads->collection((array) ($options['Memo'] ?? [])),
            'search_attributes' => isset($options['SearchAttributes'])
                ? new SearchAttributes(['indexed_fields' => $this->payloads->collection((array) $options['SearchAttributes'])])
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
            'args' => $this->payloads->payloads($command->getPayloads()),
            'headers' => $this->payloads->headerFields($command),
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
            'arguments' => $this->payloads->payloads($command->getPayloads()),
            'workflow_run_timeout' => ProtoTime::optionalDuration($options['WorkflowRunTimeout'] ?? 0),
            'workflow_task_timeout' => ProtoTime::optionalDuration($options['WorkflowTaskTimeout'] ?? 0),
            'headers' => $this->payloads->headerFields($command),
        ]);
    }

    private function updateResponse(RunState $run, UpdateResponse $response): WorkflowCommand
    {
        $updateId = (string) $response->getOptions()['id'];
        $result = new CoreUpdateResponse(['protocol_instance_id' => $run->updateProtocolInstanceId($updateId)]);
        $failure = $response->getFailure();

        match (true) {
            $failure !== null => $result->setRejected($this->payloads->failure($failure)),
            $response->getCommand() === UpdateResponse::COMMAND_VALIDATED => $result->setAccepted(new GPBEmpty()),
            default => $result->setCompleted($this->payloads->firstPayload($response->getPayloads())),
        };

        if ($response->getCommand() === UpdateResponse::COMMAND_COMPLETED || $failure !== null) {
            $run->finishUpdate($updateId);
        }

        return new WorkflowCommand(['update_response' => $result]);
    }

    /**
     * @return array<string, Payload>
     */
    private function typedSearchAttributePayloads(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $name => $attribute) {
            if ($attribute['operation'] !== self::SEARCH_ATTRIBUTE_SET) {
                $result[$name] = $this->payloads->payload(null);
                continue;
            }

            $payload = $this->payloads->payload($attribute['value']);
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
            'summary' => $summary ? $this->payloads->payload($summary) : null,
            'details' => $details ? $this->payloads->payload($details) : null,
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

    private function retryPolicy(?array $retry): ?RetryPolicy
    {
        if ($retry === null) {
            return null;
        }

        return new RetryPolicy([
            'initial_interval' => ProtoTime::optionalDuration($retry['initial_interval'] ?? null),
            'backoff_coefficient' => (float) ($retry['backoff_coefficient'] ?? 0),
            'maximum_interval' => ProtoTime::optionalDuration($retry['maximum_interval'] ?? null),
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
