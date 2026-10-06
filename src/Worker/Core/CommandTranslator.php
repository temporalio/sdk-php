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
use Temporal\Common\SearchAttributes\ValueType;
use Temporal\Exception\Failure\ApplicationFailure;
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

/**
 * @internal
 */
final class CommandTranslator
{
    private const SIDE_EFFECT_TIMEOUT_SECONDS = 60;
    private const FIRST_ATTEMPT = 1;
    private const NEGATIVE_TIMER_MESSAGE = 'negative duration provided %dms';
    private const NEGATIVE_TIMER_TYPE = 'errorString';
    private const SEARCH_ATTRIBUTE_SET = 'set';

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
            return self::emit($commands, $this->updateResponse($run, $command));
        }

        \assert($command instanceof RequestInterface);

        return match ($command->getName()) {
            Request\ExecuteActivity::NAME => self::emit($commands, $this->executeActivity($run, $command)),
            Request\ExecuteLocalActivity::NAME => self::emit($commands, $this->executeLocalActivity($run, $command, $tick)),
            Request\NewTimer::NAME => $this->newTimer($run, $command, $tick, $commands),
            Request\CompleteWorkflow::NAME => self::emit($commands, $this->completeWorkflow($command)),
            Request\ContinueAsNew::NAME => self::emit($commands, $this->continueAsNew($command)),
            Request\Cancel::NAME => $this->cancel($run, $command, $tick, $commands),
            Request\GetVersion::NAME => $this->getVersion($run, $command, $tick, $commands),
            Request\SideEffect::NAME => self::emit($commands, $this->sideEffect($run, $command, $tick)),
            Request\ExecuteChildWorkflow::NAME => self::emit($commands, $this->executeChildWorkflow($run, $command)),
            Request\GetChildWorkflowExecution::NAME => $this->childExecution($run, $command, $tick),
            Request\SignalExternalWorkflow::NAME => self::emit($commands, $this->signalExternal($run, $command)),
            Request\CancelExternalWorkflow::NAME => self::emit($commands, $this->cancelExternal($run, $command)),
            Request\UpsertSearchAttributes::NAME => self::emit($commands, $this->upsertSearchAttributes($command)),
            Request\UpsertTypedSearchAttributes::NAME => self::emit($commands, $this->upsertTypedSearchAttributes($command)),
            Request\UpsertMemo::NAME => self::emit($commands, $this->upsertMemo($command)),
            Request\Panic::NAME => self::emit($commands, $this->panic($command)),
            default => throw new \LogicException(\sprintf('Command "%s" is not supported by the sdk-core transport', $command->getName())),
        };
    }

    /**
     * @param list<WorkflowCommand> $commands
     * @return array{}
     */
    private static function emit(array &$commands, WorkflowCommand $command): array
    {
        $commands[] = $command;

        return [];
    }

    /**
     * @param array<string, Payload> $fields
     */
    private static function searchAttributes(array $fields): WorkflowCommand
    {
        return new WorkflowCommand(['upsert_workflow_search_attributes' => new UpsertWorkflowSearchAttributes([
            'search_attributes' => new SearchAttributes(['indexed_fields' => $fields]),
        ])]);
    }

    private static function searchAttributeType(ValueType $type): string
    {
        return match ($type) {
            ValueType::Bool => 'Bool',
            ValueType::Float => 'Double',
            ValueType::Int => 'Int',
            ValueType::Keyword => 'Keyword',
            ValueType::KeywordList => 'KeywordList',
            ValueType::Text => 'Text',
            ValueType::Datetime => 'Datetime',
        };
    }

    private function executeActivity(RunState $run, RequestInterface $command): WorkflowCommand
    {
        /** @var array{name: string, options: array{ActivityID?: string, TaskQueueName?: string|null, Summary?: string, ...<string, mixed>}} $request */
        $request = $command->getOptions();
        $options = $request['options'];
        $seq = $run->bind($command->getID(), RunState::ACTIVITY);
        $cancellationType = ($options['WaitForCancellation'] ?? false) === true
            ? ActivityCancellationType::WAIT_CANCELLATION_COMPLETED
            : ActivityCancellationType::TRY_CANCEL;
        if ($cancellationType === ActivityCancellationType::TRY_CANCEL) {
            $run->markTryCancel($seq);
        }

        return new WorkflowCommand([
            'schedule_activity' => new ScheduleActivity([
                'seq' => $seq,
                'activity_id' => (string) (($options['ActivityID'] ?? '') ?: $seq),
                'activity_type' => $request['name'],
                'task_queue' => ($options['TaskQueueName'] ?? '') ?: $this->taskQueue,
                'headers' => $this->payloads->headerFields($command),
                'arguments' => $this->payloads->payloads($command->getPayloads()),
                'schedule_to_close_timeout' => ProtoTime::optionalDuration($options['ScheduleToCloseTimeout'] ?? 0),
                'schedule_to_start_timeout' => ProtoTime::optionalDuration($options['ScheduleToStartTimeout'] ?? 0),
                'start_to_close_timeout' => ProtoTime::optionalDuration($options['StartToCloseTimeout'] ?? 0),
                'heartbeat_timeout' => ProtoTime::optionalDuration($options['HeartbeatTimeout'] ?? 0),
                'retry_policy' => $this->retryPolicy($options['RetryPolicy'] ?? null),
                'cancellation_type' => $cancellationType,
                'priority' => $this->priority($options['Priority'] ?? null),
            ]),
            'user_metadata' => $this->userMetadata($options['Summary'] ?? ''),
        ]);
    }

    private function executeLocalActivity(RunState $run, RequestInterface $command, TickInfo $tick): WorkflowCommand
    {
        /** @var array{name: string, options: array{Summary?: string, ...<string, mixed>}} $request */
        $request = $command->getOptions();
        $options = $request['options'];

        return $this->localActivity($run, $request['name'], $command, $tick, $options['Summary'] ?? '', [
            'headers' => $this->payloads->headerFields($command),
            'schedule_to_close_timeout' => ProtoTime::optionalDuration($options['ScheduleToCloseTimeout'] ?? 0),
            'start_to_close_timeout' => ProtoTime::optionalDuration($options['StartToCloseTimeout'] ?? 0),
            'retry_policy' => $this->retryPolicy($options['RetryPolicy'] ?? null),
            'cancellation_type' => ActivityCancellationType::WAIT_CANCELLATION_COMPLETED,
        ]);
    }

    private function sideEffect(RunState $run, RequestInterface $command, TickInfo $tick): WorkflowCommand
    {
        return $this->localActivity($run, ActivityTasks::SIDE_EFFECT, $command, $tick, $command->getOptions()['summary'] ?? '', [
            'schedule_to_close_timeout' => new Duration(['seconds' => self::SIDE_EFFECT_TIMEOUT_SECONDS]),
        ]);
    }

    private function localActivity(RunState $run, string $type, RequestInterface $command, TickInfo $tick, string $summary, array $fields): WorkflowCommand
    {
        $seq = $run->bind($command->getID(), RunState::LOCAL_ACTIVITY);

        return new WorkflowCommand([
            'schedule_local_activity' => $run->localActivities->schedule(new ScheduleLocalActivity([
                'seq' => $seq,
                'activity_id' => (string) $seq,
                'activity_type' => $type,
                'attempt' => self::FIRST_ATTEMPT,
                'original_schedule_time' => ProtoTime::timestamp($tick->time),
                'arguments' => $this->payloads->payloads($command->getPayloads()),
            ] + $fields)),
            'user_metadata' => $this->userMetadata($summary),
        ]);
    }

    /**
     * @param list<WorkflowCommand> $commands
     * @return list<CommandInterface>
     */
    private function newTimer(RunState $run, RequestInterface $command, TickInfo $tick, array &$commands): array
    {
        $options = $command->getOptions();
        $ms = (int) ($options['ms'] ?? 0);
        if ($ms < 0) {
            return [new FailureResponse(
                new ApplicationFailure(\sprintf(self::NEGATIVE_TIMER_MESSAGE, $ms), self::NEGATIVE_TIMER_TYPE, false),
                $command->getID(),
                $tick,
            )];
        }
        if ($ms === 0) {
            return [new SuccessResponse(null, $command->getID(), $tick)];
        }

        return self::emit($commands, new WorkflowCommand([
            'start_timer' => new StartTimer([
                'seq' => $run->bind($command->getID(), RunState::TIMER),
                'start_to_fire_timeout' => ProtoTime::duration($ms * ProtoTime::NANOS_PER_MILLISECOND),
            ]),
            'user_metadata' => $this->userMetadata($options['summary'] ?? ''),
        ]));
    }

    /**
     * @param list<WorkflowCommand> $commands
     * @return list<CommandInterface>
     */
    private function cancel(RunState $run, RequestInterface $command, TickInfo $tick, array &$commands): array
    {
        $responses = [];
        foreach ($command->getOptions()['ids'] ?? [] as $requestId) {
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

        return [...$responses, new SuccessResponse(null, $command->getID(), $tick)];
    }

    /**
     * @param list<WorkflowCommand> $commands
     * @return list<CommandInterface>
     */
    private function getVersion(RunState $run, RequestInterface $command, TickInfo $tick, array &$commands): array
    {
        /** @var array{changeID: string, minSupported: int, maxSupported: int} $options */
        $options = $command->getOptions();
        $version = $run->patches->version(
            $options['changeID'],
            $options['minSupported'],
            $options['maxSupported'],
            $tick->isReplaying,
            $commands,
        );

        return [new SuccessResponse($this->payloads->encode([$version]), $command->getID(), $tick)];
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

    private function continueAsNew(RequestInterface $command): WorkflowCommand
    {
        /** @var array{name: string, options?: array{TaskQueueName?: string, ...<string, mixed>}} $request */
        $request = $command->getOptions();
        $options = $request['options'] ?? [];

        return new WorkflowCommand(['continue_as_new_workflow_execution' => new ContinueAsNewWorkflowExecution([
            'workflow_type' => $request['name'],
            'task_queue' => ($options['TaskQueueName'] ?? '') ?: $this->taskQueue,
            'arguments' => $this->payloads->payloads($command->getPayloads()),
            'workflow_run_timeout' => ProtoTime::optionalDuration($options['WorkflowRunTimeout'] ?? 0),
            'workflow_task_timeout' => ProtoTime::optionalDuration($options['WorkflowTaskTimeout'] ?? 0),
            'headers' => $this->payloads->headerFields($command),
        ])]);
    }

    private function executeChildWorkflow(RunState $run, RequestInterface $command): WorkflowCommand
    {
        /** @var array{name: string, options: array{Namespace?: string, WorkflowID?: string|null, TaskQueueName?: string, StaticSummary?: string, StaticDetails?: string, ...<string, mixed>}} $request */
        $request = $command->getOptions();
        $options = $request['options'];
        $seq = $run->bind($command->getID(), RunState::CHILD);
        $workflowId = ($options['WorkflowID'] ?? '') ?: $run->runId . '_' . $seq;
        $run->startChild($command->getID(), $workflowId);

        return new WorkflowCommand([
            'start_child_workflow_execution' => new StartChildWorkflowExecution([
                'seq' => $seq,
                'namespace' => ($options['Namespace'] ?? '') ?: $this->namespace,
                'workflow_id' => $workflowId,
                'workflow_type' => $request['name'],
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
            ]),
            'user_metadata' => $this->userMetadata($options['StaticSummary'] ?? '', $options['StaticDetails'] ?? ''),
        ]);
    }

    /**
     * @return list<CommandInterface>
     */
    private function childExecution(RunState $run, RequestInterface $command, TickInfo $tick): array
    {
        /** @var array{id: int} $options */
        $options = $command->getOptions();
        $execution = $run->childExecution($options['id']);
        if ($execution !== null) {
            return [$this->resolutions->childExecution($command->getID(), $execution, $tick)];
        }
        $run->awaitChildExecution($options['id'], $command->getID());

        return [];
    }

    private function signalExternal(RunState $run, RequestInterface $command): WorkflowCommand
    {
        /** @var array{namespace: string, workflowID: string, runID: ?string, signal: string, childWorkflowOnly: bool} $options */
        $options = $command->getOptions();
        $signal = new SignalExternalWorkflowExecution([
            'seq' => $run->bind($command->getID(), RunState::SIGNAL_EXTERNAL),
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

        return new WorkflowCommand(['signal_external_workflow_execution' => $signal]);
    }

    private function cancelExternal(RunState $run, RequestInterface $command): WorkflowCommand
    {
        /** @var array{namespace: string, workflowID: string, runID: ?string} $options */
        $options = $command->getOptions();

        return new WorkflowCommand(['request_cancel_external_workflow_execution' => new RequestCancelExternalWorkflowExecution([
            'seq' => $run->bind($command->getID(), RunState::CANCEL_EXTERNAL),
            'workflow_execution' => new NamespacedWorkflowExecution([
                'namespace' => $options['namespace'] ?: $this->namespace,
                'workflow_id' => $options['workflowID'],
                'run_id' => $options['runID'] ?? '',
            ]),
        ])]);
    }

    private function upsertSearchAttributes(RequestInterface $command): WorkflowCommand
    {
        /** @var array{searchAttributes: object} $options */
        $options = $command->getOptions();

        return self::searchAttributes($this->payloads->collection((array) $options['searchAttributes']));
    }

    private function upsertTypedSearchAttributes(RequestInterface $command): WorkflowCommand
    {
        /** @var array{search_attributes: object} $options */
        $options = $command->getOptions();

        return self::searchAttributes($this->typedSearchAttributePayloads((array) $options['search_attributes']));
    }

    private function upsertMemo(RequestInterface $command): WorkflowCommand
    {
        /** @var array{memo: object} $options */
        $options = $command->getOptions();

        return new WorkflowCommand(['modify_workflow_properties' => new ModifyWorkflowProperties([
            'upserted_memo' => new Memo(['fields' => $this->payloads->collection((array) $options['memo'])]),
        ])]);
    }

    private function panic(RequestInterface $command): WorkflowCommand
    {
        $panic = $command->getFailure() ?? new \RuntimeException($command->getOptions()['message'] ?? 'Workflow panic');
        if (!$this->failWorkflowOnPanic) {
            throw $panic;
        }

        return new WorkflowCommand(['fail_workflow_execution' => new FailWorkflowExecution([
            'failure' => $this->payloads->failure($panic),
        ])]);
    }

    private function updateResponse(RunState $run, UpdateResponse $response): WorkflowCommand
    {
        /** @var array{id: int|string} $options */
        $options = $response->getOptions();
        $updateId = (string) $options['id'];
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
            /** @var \ArrayAccess<string, string> $metadata */
            $metadata = $payload->getMetadata();
            $metadata['type'] = self::searchAttributeType(ValueType::from($attribute['type']));
            $result[$name] = $payload;
        }

        return $result;
    }

    private function userMetadata(string $summary, string $details = ''): ?UserMetadata
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
}
