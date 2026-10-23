<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use Coresdk\Workflow_commands\ActivityCancellationType;
use Coresdk\Workflow_commands\WorkflowCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Common\SearchAttributes\ValueType;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Transport\Request\UndefinedResponse;
use Temporal\Worker\Core\ActivityTasks;
use Temporal\Worker\Core\CommandTranslator;
use Temporal\Worker\Core\PayloadMapper;
use Temporal\Worker\Core\ResolutionMapper;
use Temporal\Worker\Core\RunState;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\Request;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\Client\UpdateResponse;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Workflow\WorkflowExecution;

final class CommandTranslatorTestCase extends TestCase
{
    private const TIMER_ID = 101;
    private const ACTIVITY_ID = 102;
    private const LOCAL_ACTIVITY_ID = 103;
    private const CHILD_ID = 104;
    private const UNKNOWN_ID = 105;
    private const WAITER_ID = 106;

    public static function provideCommands(): iterable
    {
        yield 'success response' => [
            static fn(): SuccessClientResponse => new SuccessClientResponse(1),
            static function (array $messages, array $commands): void {
                self::assertSame([[], []], [$messages, $commands]);
            },
        ];
        yield 'failed response' => [
            static fn(): FailedClientResponse => new FailedClientResponse(1, new \DomainException('response failed')),
            static function (\DomainException $error): void {
                self::assertSame('response failed', $error->getMessage());
            },
        ];
        yield 'undefined response' => [
            static fn(): UndefinedResponse => new UndefinedResponse('no such command'),
            static function (\LogicException $error): void {
                self::assertSame('no such command', $error->getMessage());
            },
        ];
        yield 'update validated' => [
            static function (RunState $run): UpdateResponse {
                $run->startUpdate('upd', 'protocol');
                return new UpdateResponse(UpdateResponse::COMMAND_VALIDATED, null, null, 'upd');
            },
            static function (array $messages, array $commands, RunState $run): void {
                $response = $commands[0]->getUpdateResponse();
                self::assertSame(['protocol', 'accepted', 'protocol'], [$response->getProtocolInstanceId(), $response->getResponse(), $run->updateProtocolInstanceId('upd')]);
            },
        ];
        yield 'update completed' => [
            static function (RunState $run): UpdateResponse {
                $run->startUpdate('upd', 'protocol');
                return new UpdateResponse(UpdateResponse::COMMAND_COMPLETED, EncodedValues::fromValues(['done']), null, 'upd');
            },
            static function (array $messages, array $commands, RunState $run): void {
                $response = $commands[0]->getUpdateResponse();
                self::assertSame('completed', $response->getResponse());
                self::assertSame('"done"', $response->getCompleted()->getData());
                self::assertSame('upd', $run->updateProtocolInstanceId('upd'));
            },
        ];
        yield 'update rejected' => [
            static fn(): UpdateResponse => new UpdateResponse(UpdateResponse::COMMAND_VALIDATED, null, new \RuntimeException('invalid'), 'upd'),
            static function (array $messages, array $commands): void {
                self::assertSame('invalid', $commands[0]->getUpdateResponse()->getRejected()->getMessage());
            },
        ];
        yield 'activity with every option' => [
            static fn(): Request => new Request('ExecuteActivity', ['name' => 'Act', 'options' => [
                'Summary' => 'sum',
                'ActivityID' => 'act-id',
                'TaskQueueName' => 'other',
                'StartToCloseTimeout' => 1_000_000_000,
                'RetryPolicy' => [
                    'initial_interval' => ['seconds' => 1],
                    'backoff_coefficient' => 2.0,
                    'maximum_interval' => ['seconds' => 10, 'nanos' => 5],
                    'maximum_attempts' => 3,
                    'non_retryable_error_types' => ['E'],
                ],
                'Priority' => ['priority_key' => 2, 'fairness_key' => 'f', 'fairness_weight' => 1.5],
                'WaitForCancellation' => true,
            ]], EncodedValues::fromValues(['arg'])),
            static function (array $messages, array $commands, RunState $run): void {
                $activity = $commands[0]->getScheduleActivity();
                self::assertSame([], $messages);
                self::assertSame(['act-id', 'Act', 'other', 1], [$activity->getActivityId(), $activity->getActivityType(), $activity->getTaskQueue(), (int) $activity->getStartToCloseTimeout()->getSeconds()]);
                self::assertSame(ActivityCancellationType::WAIT_CANCELLATION_COMPLETED, $activity->getCancellationType());
                self::assertSame([3, 5, ['E']], [$activity->getRetryPolicy()->getMaximumAttempts(), $activity->getRetryPolicy()->getMaximumInterval()->getNanos(), \iterator_to_array($activity->getRetryPolicy()->getNonRetryableErrorTypes())]);
                self::assertSame(['f', 1.5], [$activity->getPriority()->getFairnessKey(), $activity->getPriority()->getFairnessWeight()]);
                self::assertSame('"sum"', $commands[0]->getUserMetadata()->getSummary()->getData());
                self::assertSame('"arg"', $activity->getArguments()[0]->getData());
                self::assertFalse($run->takeTryCancel(1));
            },
        ];
        yield 'activity with defaults tries to cancel' => [
            static fn(): Request => new Request('ExecuteActivity', ['name' => 'Act', 'options' => []]),
            static function (array $messages, array $commands, RunState $run): void {
                $activity = $commands[0]->getScheduleActivity();
                self::assertSame(['1', 'queue', ActivityCancellationType::TRY_CANCEL], [$activity->getActivityId(), $activity->getTaskQueue(), $activity->getCancellationType()]);
                self::assertNull($activity->getRetryPolicy());
                self::assertNull($activity->getPriority());
                self::assertNull($commands[0]->getUserMetadata());
                self::assertTrue($run->takeTryCancel(1));
            },
        ];
        yield 'local activity' => [
            static fn(): Request => new Request('ExecuteLocalActivity', ['name' => 'Local', 'options' => ['Summary' => 'local', 'StartToCloseTimeout' => 2_000_000_000]]),
            static function (array $messages, array $commands): void {
                $activity = $commands[0]->getScheduleLocalActivity();
                self::assertSame(['1', 'Local', 1, 2, 1_700_000_000], [$activity->getActivityId(), $activity->getActivityType(), $activity->getAttempt(), (int) $activity->getStartToCloseTimeout()->getSeconds(), (int) $activity->getOriginalScheduleTime()->getSeconds()]);
                self::assertSame('"local"', $commands[0]->getUserMetadata()->getSummary()->getData());
            },
        ];
        yield 'timer with summary' => [
            static fn(): Request => new Request('NewTimer', ['ms' => 1500, 'summary' => 'wait']),
            static function (array $messages, array $commands): void {
                $timer = $commands[0]->getStartTimer();
                self::assertSame([1, 1, 500_000_000], [$timer->getSeq(), (int) $timer->getStartToFireTimeout()->getSeconds(), $timer->getStartToFireTimeout()->getNanos()]);
                self::assertSame('"wait"', $commands[0]->getUserMetadata()->getSummary()->getData());
            },
        ];
        yield 'complete with result' => [
            static fn(): Request => new Request('CompleteWorkflow', [], EncodedValues::fromValues(['result'])),
            static function (array $messages, array $commands): void {
                self::assertSame('"result"', $commands[0]->getCompleteWorkflowExecution()->getResult()->getData());
            },
        ];
        yield 'complete with cancellation' => [
            static fn(): Request => self::failed(new Request('CompleteWorkflow'), new CanceledFailure('canceled')),
            static function (array $messages, array $commands): void {
                self::assertTrue($commands[0]->hasCancelWorkflowExecution());
            },
        ];
        yield 'complete with failure' => [
            static fn(): Request => self::failed(new Request('CompleteWorkflow'), new \RuntimeException('workflow failed')),
            static function (array $messages, array $commands): void {
                self::assertSame('workflow failed', $commands[0]->getFailWorkflowExecution()->getFailure()->getMessage());
            },
        ];
        yield 'continue as new with options' => [
            static fn(): Request => new Request('ContinueAsNew', ['name' => 'Next', 'options' => ['TaskQueueName' => 'next-queue', 'WorkflowRunTimeout' => 3_000_000_000]]),
            static function (array $messages, array $commands): void {
                $next = $commands[0]->getContinueAsNewWorkflowExecution();
                self::assertSame(['Next', 'next-queue', 3], [$next->getWorkflowType(), $next->getTaskQueue(), (int) $next->getWorkflowRunTimeout()->getSeconds()]);
            },
        ];
        yield 'continue as new without options' => [
            static fn(): Request => new Request('ContinueAsNew', ['name' => 'Next']),
            static function (array $messages, array $commands): void {
                self::assertSame('queue', $commands[0]->getContinueAsNewWorkflowExecution()->getTaskQueue());
            },
        ];
        yield 'cancel every bound command' => [
            static function (RunState $run): Request {
                $run->bind(self::TIMER_ID, RunState::TIMER);
                $run->bind(self::ACTIVITY_ID, RunState::ACTIVITY);
                $run->bind(self::LOCAL_ACTIVITY_ID, RunState::LOCAL_ACTIVITY);
                $run->bind(self::CHILD_ID, RunState::CHILD);
                return new Request('Cancel', ['ids' => [self::TIMER_ID, self::ACTIVITY_ID, self::LOCAL_ACTIVITY_ID, self::CHILD_ID, self::UNKNOWN_ID]]);
            },
            static function (array $messages, array $commands, RunState $run): void {
                self::assertSame(
                    ['cancel_timer', 'request_cancel_activity', 'request_cancel_local_activity', 'cancel_child_workflow_execution'],
                    \array_map(static fn(WorkflowCommand $command): string => $command->getVariant(), $commands),
                );
                self::assertInstanceOf(FailureResponse::class, $messages[0]);
                self::assertSame(self::TIMER_ID, $messages[0]->getID());
                self::assertInstanceOf(CanceledFailure::class, $messages[0]->getFailure());
                self::assertInstanceOf(SuccessResponse::class, $messages[1]);
                self::assertNull($run->command(self::TIMER_ID));
            },
        ];
        yield 'get version' => [
            static fn(): Request => new Request('GetVersion', ['changeID' => 'change', 'minSupported' => 1, 'maxSupported' => 4]),
            static function (array $messages, array $commands): void {
                self::assertSame(4, $messages[0]->getPayloads()->getValue(0));
                self::assertSame('change-4', $commands[0]->getSetPatchMarker()->getPatchId());
            },
        ];
        yield 'side effect' => [
            static fn(): Request => new Request('SideEffect', ['summary' => 'effect'], EncodedValues::fromValues([7])),
            static function (array $messages, array $commands): void {
                $activity = $commands[0]->getScheduleLocalActivity();
                self::assertSame([ActivityTasks::SIDE_EFFECT, 60, '7'], [$activity->getActivityType(), (int) $activity->getScheduleToCloseTimeout()->getSeconds(), $activity->getArguments()[0]->getData()]);
                self::assertSame('"effect"', $commands[0]->getUserMetadata()->getSummary()->getData());
            },
        ];
        yield 'child with every option' => [
            static fn(): Request => new Request('ExecuteChildWorkflow', ['name' => 'Child', 'options' => [
                'Namespace' => 'child-ns',
                'WorkflowID' => 'child-id',
                'TaskQueueName' => 'child-queue',
                'CronSchedule' => '* * * * *',
                'Memo' => ['m' => 1],
                'SearchAttributes' => ['s' => 'v'],
                'StaticSummary' => 'summary',
                'StaticDetails' => 'details',
            ]]),
            static function (array $messages, array $commands, RunState $run): void {
                $child = $commands[0]->getStartChildWorkflowExecution();
                self::assertSame(['child-ns', 'child-id', 'child-queue', '* * * * *'], [$child->getNamespace(), $child->getWorkflowId(), $child->getTaskQueue(), $child->getCronSchedule()]);
                self::assertSame('1', $child->getMemo()['m']->getData());
                self::assertSame('"v"', $child->getSearchAttributes()->getIndexedFields()['s']->getData());
                self::assertSame('"details"', $commands[0]->getUserMetadata()->getDetails()->getData());
                self::assertSame(['child-id', 'child-run'], $run->childStarted($run->requestId(1), 'child-run'));
            },
        ];
        yield 'child with defaults' => [
            static fn(): Request => new Request('ExecuteChildWorkflow', ['name' => 'Child', 'options' => []]),
            static function (array $messages, array $commands): void {
                $child = $commands[0]->getStartChildWorkflowExecution();
                self::assertSame(['ns', 'run_1', 'queue'], [$child->getNamespace(), $child->getWorkflowId(), $child->getTaskQueue()]);
                self::assertNull($child->getSearchAttributes());
                self::assertNull($commands[0]->getUserMetadata());
            },
        ];
        yield 'started child execution' => [
            static function (RunState $run): Request {
                $run->startChild(self::CHILD_ID, 'child-id');
                $run->childStarted(self::CHILD_ID, 'child-run');
                return new Request('GetChildWorkflowExecution', ['id' => self::CHILD_ID]);
            },
            static function (array $messages): void {
                $execution = $messages[0]->getPayloads()->getValue(0, WorkflowExecution::class);
                self::assertSame(['child-id', 'child-run'], [$execution->getID(), $execution->getRunID()]);
            },
        ];
        yield 'pending child execution' => [
            static fn(): Request => new Request('GetChildWorkflowExecution', ['id' => self::CHILD_ID]),
            static function (array $messages, array $commands, RunState $run): void {
                self::assertSame([], $messages);
                self::assertCount(1, $run->takeChildWaiters(self::CHILD_ID));
            },
        ];
        yield 'signal child' => [
            static fn(): Request => new Request('SignalExternalWorkflow', ['namespace' => '', 'workflowID' => 'child-id', 'runID' => null, 'signal' => 'go', 'childWorkflowOnly' => true]),
            static function (array $messages, array $commands): void {
                $signal = $commands[0]->getSignalExternalWorkflowExecution();
                self::assertSame(['go', 'child-id'], [$signal->getSignalName(), $signal->getChildWorkflowId()]);
            },
        ];
        yield 'signal external' => [
            static fn(): Request => new Request('SignalExternalWorkflow', ['namespace' => '', 'workflowID' => 'wf-id', 'runID' => 'run', 'signal' => 'go', 'childWorkflowOnly' => false]),
            static function (array $messages, array $commands): void {
                $execution = $commands[0]->getSignalExternalWorkflowExecution()->getWorkflowExecution();
                self::assertSame(['ns', 'wf-id', 'run'], [$execution->getNamespace(), $execution->getWorkflowId(), $execution->getRunId()]);
            },
        ];
        yield 'cancel external' => [
            static fn(): Request => new Request('CancelExternalWorkflow', ['namespace' => '', 'workflowID' => 'wf-id', 'runID' => null]),
            static function (array $messages, array $commands): void {
                $execution = $commands[0]->getRequestCancelExternalWorkflowExecution()->getWorkflowExecution();
                self::assertSame(['ns', 'wf-id', ''], [$execution->getNamespace(), $execution->getWorkflowId(), $execution->getRunId()]);
            },
        ];
        yield 'upsert search attributes' => [
            static fn(): Request => new Request('UpsertWorkflowSearchAttributes', ['searchAttributes' => ['attr' => 'value']]),
            static function (array $messages, array $commands): void {
                self::assertSame('"value"', $commands[0]->getUpsertWorkflowSearchAttributes()->getSearchAttributes()->getIndexedFields()['attr']->getData());
            },
        ];
        yield 'upsert typed search attributes' => [
            static fn(): Request => new Request('UpsertWorkflowTypedSearchAttributes', ['search_attributes' => \array_combine(
                \array_map(static fn(ValueType $type): string => $type->value, ValueType::cases()),
                \array_map(static fn(ValueType $type): array => ['type' => $type->value, 'operation' => 'set', 'value' => 'v'], ValueType::cases()),
            )]),
            static function (array $messages, array $commands): void {
                $fields = $commands[0]->getUpsertWorkflowSearchAttributes()->getSearchAttributes()->getIndexedFields();
                foreach (ValueType::cases() as $type) {
                    self::assertSame($type, ValueType::fromMetadata($fields[$type->value]->getMetadata()['type']));
                }
            },
        ];
        yield 'upsert memo' => [
            static fn(): Request => new Request('UpsertMemo', ['memo' => ['key' => 'value']]),
            static function (array $messages, array $commands): void {
                self::assertSame('"value"', $commands[0]->getModifyWorkflowProperties()->getUpsertedMemo()->getFields()['key']->getData());
            },
        ];
        yield 'panic fails the workflow' => [
            static fn(): Request => self::failed(new Request('Panic'), new \RuntimeException('panicked')),
            static function (array $messages, array $commands): void {
                self::assertSame('panicked', $commands[0]->getFailWorkflowExecution()->getFailure()->getMessage());
            },
            true,
        ];
        yield 'panic fails the task' => [
            static fn(): Request => self::failed(new Request('Panic'), new ApplicationFailure('panicked', 'Type', false)),
            static function (ApplicationFailure $error): void {
                self::assertSame('panicked', $error->getOriginalMessage());
            },
        ];
        yield 'panic without failure' => [
            static fn(): Request => new Request('Panic', ['message' => 'lost']),
            static function (\RuntimeException $error): void {
                self::assertSame('lost', $error->getMessage());
            },
        ];
        yield 'unsupported command' => [
            static fn(): Request => new Request('Unknown'),
            static function (\LogicException $error): void {
                self::assertSame('Command "Unknown" is not supported by the sdk-core transport', $error->getMessage());
            },
        ];
    }

    #[DataProvider('provideCommands')]
    public function testTranslate(\Closure $command, \Closure $assert, bool $failWorkflowOnPanic = false): void
    {
        $payloads = new PayloadMapper(DataConverter::createDefault());
        $translator = new CommandTranslator($payloads, new ResolutionMapper($payloads, 'ns'), 'ns', 'queue', $failWorkflowOnPanic);
        $run = new RunState('run');
        $commands = [];

        try {
            $messages = $translator->translate($run, $command($run), new TickInfo(new \DateTimeImmutable('@1700000000')), $commands);
        } catch (\Throwable $error) {
            $assert($error, $commands, $run);
            return;
        }
        $assert($messages, $commands, $run);
    }

    private static function failed(Request $request, \Throwable $failure): Request
    {
        $request->setFailure($failure);

        return $request;
    }
}
