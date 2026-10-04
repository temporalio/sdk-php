<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use Coresdk\Activity_result\ActivityResolution;
use Coresdk\Activity_result\Cancellation as ActivityCancellation;
use Coresdk\Activity_result\DoBackoff;
use Coresdk\Activity_result\Failure as ActivityFailure;
use Coresdk\Activity_result\Success as ActivitySuccess;
use Coresdk\Child_workflow\Cancellation as ChildCancellation;
use Coresdk\Child_workflow\ChildWorkflowCancellationType;
use Coresdk\Child_workflow\ChildWorkflowResult;
use Coresdk\Child_workflow\Failure as ChildFailure;
use Coresdk\Child_workflow\Success as ChildSuccess;
use Coresdk\Workflow_activation\CancelWorkflow;
use Coresdk\Workflow_activation\DoUpdate;
use Coresdk\Workflow_activation\FireTimer;
use Coresdk\Workflow_activation\InitializeWorkflow;
use Coresdk\Workflow_activation\NotifyHasPatch;
use Coresdk\Workflow_activation\QueryWorkflow;
use Coresdk\Workflow_activation\RemoveFromCache;
use Coresdk\Workflow_activation\ResolveActivity;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecution;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStart;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStartCancelled;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStartFailure;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStartSuccess;
use Coresdk\Workflow_activation\ResolveRequestCancelExternalWorkflow;
use Coresdk\Workflow_activation\ResolveSignalExternalWorkflow;
use Coresdk\Workflow_activation\SignalWorkflow;
use Coresdk\Workflow_activation\UpdateRandomSeed;
use Coresdk\Workflow_activation\WorkflowActivation;
use Coresdk\Workflow_activation\WorkflowActivationJob;
use Coresdk\Workflow_commands\ActivityCancellationType;
use Coresdk\Workflow_commands\QueryResult;
use Coresdk\Workflow_commands\WorkflowCommand;
use Coresdk\Workflow_completion\WorkflowActivationCompletion;
use PHPUnit\Framework\Attributes\DataProvider;
use Google\Protobuf\Duration;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Failure\V1\ActivityFailureInfo;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\CanceledFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Common\SearchAttributes\ValueType;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ActivityFailure as ActivityFailureException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Worker\Core\PayloadMapper;
use Temporal\Worker\Core\ResolutionMapper;
use Temporal\Worker\Core\RunState;
use Temporal\Worker\Core\WorkflowActivations;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\Request;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;

final class WorkflowActivationsTestCase extends TestCase
{
    private const VERSIONING_BEHAVIOR = 2;

    public static function provideActivations(): iterable
    {
        yield 'initialize with last completion result' => [
            [[new WorkflowActivationJob(['initialize_workflow' => new InitializeWorkflow([
                'workflow_type' => 'Wf',
                'workflow_id' => 'wf-id',
                'arguments' => [self::payload('arg')],
                'last_completion_result' => new Payloads(['payloads' => [self::payload('last')]]),
            ])])]],
            static fn(): array => [],
            static function (array $completions, array $dispatched): void {
                self::assertSame(self::VERSIONING_BEHAVIOR, $completions[0]->getSuccessful()->getVersioningBehavior());
                self::assertSame('StartWorkflow', $dispatched[0]->getName());
                self::assertSame(1, $dispatched[0]->getOptions()['lastCompletion']);
                self::assertSame(['arg', 'last'], [$dispatched[0]->getPayloads()->getValue(0), $dispatched[0]->getPayloads()->getValue(1)]);
            },
        ];
        yield 'timer fires' => [
            [[self::initialize()], [self::job('fire_timer', new FireTimer(['seq' => 1]))]],
            self::onStart(static fn(): array => [new Request('NewTimer', ['ms' => 1000])]),
            static function (array $completions, array $dispatched): void {
                self::assertInstanceOf(SuccessResponse::class, $dispatched[1]);
            },
        ];
        yield 'local activity backoff and retry' => [
            [
                [self::initialize()],
                [self::job('resolve_activity', new ResolveActivity(['seq' => 1, 'result' => new ActivityResolution(['backoff' => new DoBackoff([
                    'attempt' => 2,
                    'backoff_duration' => new Duration(['seconds' => 3]),
                ])])]))],
                [self::job('fire_timer', new FireTimer(['seq' => 2]))],
            ],
            self::onStart(static fn(): array => [new Request('ExecuteLocalActivity', ['name' => 'Local', 'options' => []])]),
            static function (array $completions, array $dispatched): void {
                $timer = $completions[1]->getSuccessful()->getCommands()[0]->getStartTimer();
                self::assertSame([2, 3], [$timer->getSeq(), (int) $timer->getStartToFireTimeout()->getSeconds()]);
                $retry = $completions[2]->getSuccessful()->getCommands()[0]->getScheduleLocalActivity();
                self::assertSame([3, 2], [$retry->getSeq(), $retry->getAttempt()]);
                self::assertCount(1, $dispatched);
            },
        ];
        yield 'activity completed' => [
            [[self::initialize()], [self::resolveActivity(new ActivityResolution(['completed' => new ActivitySuccess(['result' => self::payload('done')])]))]],
            self::onStart(static fn(): array => [new Request('ExecuteActivity', ['name' => 'Act', 'options' => []])]),
            static function (array $completions, array $dispatched): void {
                self::assertSame('done', $dispatched[1]->getPayloads()->getValue(0));
            },
        ];
        yield 'activity failed' => [
            [[self::initialize()], [self::resolveActivity(new ActivityResolution(['failed' => new ActivityFailure(['failure' => self::applicationFailure('broken')])]))]],
            self::onStart(static fn(): array => [new Request('ExecuteActivity', ['name' => 'Act', 'options' => []])]),
            static function (array $completions, array $dispatched): void {
                self::assertSame('broken', $dispatched[1]->getFailure()->getOriginalMessage());
            },
        ];
        yield 'try-cancel activity cancelled' => [
            [[self::initialize()], [self::resolveActivity(self::cancelledActivity())]],
            self::onStart(static fn(): array => [new Request('ExecuteActivity', ['name' => 'Act', 'options' => []])]),
            static function (array $completions, array $dispatched): void {
                self::assertInstanceOf(CanceledFailure::class, $dispatched[1]->getFailure());
            },
        ];
        yield 'wait-cancel activity cancelled' => [
            [[self::initialize()], [self::resolveActivity(self::cancelledActivity())]],
            self::onStart(static fn(): array => [new Request('ExecuteActivity', ['name' => 'Act', 'options' => ['WaitForCancellation' => true]])]),
            static function (array $completions, array $dispatched): void {
                self::assertInstanceOf(ActivityFailureException::class, $dispatched[1]->getFailure());
            },
        ];
        yield 'signal' => [
            [[self::job('signal_workflow', new SignalWorkflow(['signal_name' => 'go', 'input' => [self::payload('x')]]))]],
            static fn(): array => [],
            static function (array $completions, array $dispatched): void {
                self::assertSame(['InvokeSignal', 'go', 'x'], [$dispatched[0]->getName(), $dispatched[0]->getOptions()['name'], $dispatched[0]->getPayloads()->getValue(0)]);
            },
        ];
        yield 'query answered' => [
            [[self::initialize(), self::query()]],
            self::onQuery(static fn(string $id): array => [new SuccessClientResponse($id, EncodedValues::fromValues(['answer']))]),
            static function (array $completions): void {
                self::assertSame('"answer"', self::queryResult($completions[0])->getSucceeded()->getResponse()->getData());
            },
        ];
        yield 'query failed' => [
            [[self::initialize(), self::query()]],
            self::onQuery(static fn(string $id): array => [new FailedClientResponse($id, new \RuntimeException('no answer'))]),
            static function (array $completions): void {
                self::assertSame('no answer', self::queryResult($completions[0])->getFailed()->getMessage());
            },
        ];
        yield 'query without result' => [
            [[self::initialize(), self::query()]],
            self::onQuery(static fn(): array => []),
            static function (array $completions): void {
                self::assertSame('Query produced no result', self::queryResult($completions[0])->getFailed()->getMessage());
            },
        ];
        yield 'cancel workflow' => [
            [[self::job('cancel_workflow', new CancelWorkflow())]],
            static fn(): array => [],
            static function (array $completions, array $dispatched): void {
                self::assertSame('CancelWorkflow', $dispatched[0]->getName());
            },
        ];
        yield 'update' => [
            [[self::job('do_update', new DoUpdate(['id' => 'upd', 'protocol_instance_id' => 'protocol', 'name' => 'set', 'run_validator' => false]))]],
            static fn(): array => [],
            static function (array $completions, array $dispatched): void {
                self::assertSame(['runId' => 'run-id', 'updateId' => 'upd', 'name' => 'set', 'replay' => true], $dispatched[0]->getOptions());
            },
        ];
        yield 'notified patch' => [
            [[self::job('notify_has_patch', new NotifyHasPatch(['patch_id' => 'change-3'])), self::initialize()]],
            self::onStart(static fn(): array => [new Request('GetVersion', ['changeID' => 'change', 'minSupported' => 1, 'maxSupported' => 5])]),
            static function (array $completions, array $dispatched): void {
                self::assertSame(3, $dispatched[1]->getPayloads()->getValue(0));
            },
        ];
        yield 'child start cancelled' => [
            [[self::initialize()], [self::job('resolve_child_workflow_execution_start', new ResolveChildWorkflowExecutionStart([
                'seq' => 1,
                'cancelled' => new ResolveChildWorkflowExecutionStartCancelled(['failure' => self::canceledFailure()]),
            ]))]],
            self::onStart(static fn(): array => [new Request('ExecuteChildWorkflow', ['name' => 'Child', 'options' => []])]),
            static function (array $completions, array $dispatched): void {
                self::assertInstanceOf(CanceledFailure::class, $dispatched[1]->getFailure());
            },
        ];
        yield 'child failed' => [
            [[self::initialize()], [self::childStarted()], [self::childResult(new ChildWorkflowResult(['failed' => new ChildFailure(['failure' => self::applicationFailure('child broken')])]))]],
            self::onStart(static fn(): array => [new Request('ExecuteChildWorkflow', ['name' => 'Child', 'options' => []])]),
            static function (array $completions, array $dispatched): void {
                self::assertSame('child broken', $dispatched[\count($dispatched) - 1]->getFailure()->getOriginalMessage());
            },
        ];
        yield 'child cancelled' => [
            [[self::initialize()], [self::childStarted()], [self::childResult(new ChildWorkflowResult(['cancelled' => new ChildCancellation(['failure' => self::canceledFailure()])]))]],
            self::onStart(static fn(): array => [new Request('ExecuteChildWorkflow', ['name' => 'Child', 'options' => []])]),
            static function (array $completions, array $dispatched): void {
                self::assertInstanceOf(CanceledFailure::class, $dispatched[\count($dispatched) - 1]->getFailure());
            },
        ];
        yield 'external signal delivered' => [
            [[self::initialize()], [self::job('resolve_signal_external_workflow', new ResolveSignalExternalWorkflow(['seq' => 1]))]],
            self::onStart(static fn(): array => [new Request('SignalExternalWorkflow', ['namespace' => '', 'workflowID' => 'wf', 'runID' => null, 'signal' => 's', 'childWorkflowOnly' => false])]),
            static function (array $completions, array $dispatched): void {
                self::assertInstanceOf(SuccessResponse::class, $dispatched[1]);
            },
        ];
        yield 'external cancel failed' => [
            [[self::initialize()], [self::job('resolve_request_cancel_external_workflow', new ResolveRequestCancelExternalWorkflow(['seq' => 1, 'failure' => self::applicationFailure('not found')]))]],
            self::onStart(static fn(): array => [new Request('CancelExternalWorkflow', ['namespace' => '', 'workflowID' => 'wf', 'runID' => null])]),
            static function (array $completions, array $dispatched): void {
                self::assertSame('not found', $dispatched[1]->getFailure()->getOriginalMessage());
            },
        ];
        yield 'eviction' => [
            [[self::initialize()], [self::job('remove_from_cache', new RemoveFromCache())]],
            static fn(): array => [],
            static function (array $completions, array $dispatched): void {
                self::assertSame('DestroyWorkflow', $dispatched[1]->getName());
                self::assertCount(0, $completions[1]->getSuccessful()->getCommands());
            },
        ];
        yield 'random seed update' => [
            [[self::job('update_random_seed', new UpdateRandomSeed())]],
            static fn(): array => [],
            static function (array $completions, array $dispatched): void {
                self::assertSame('successful', $completions[0]->getStatus());
                self::assertSame([], $dispatched);
            },
        ];
        yield 'unknown job' => [
            [[new WorkflowActivationJob()]],
            static fn(): array => [],
            static function (array $completions): void {
                self::assertSame('Unsupported activation job ""', $completions[0]->getFailed()->getFailure()->getMessage());
            },
        ];
    }

    /**
     * @param list<list<WorkflowActivationJob>> $activations
     */
    #[DataProvider('provideActivations')]
    public function testHandle(array $activations, \Closure $dispatch, \Closure $assert): void
    {
        $dispatched = [];
        $handler = new WorkflowActivations(DataConverter::createDefault(), static function (array $messages) use ($dispatch, &$dispatched): array {
            \array_push($dispatched, ...$messages);
            return $dispatch($messages);
        }, 'ns', 'queue', ['Wf' => self::VERSIONING_BEHAVIOR], false);

        $completions = \array_map(fn(array $jobs): WorkflowActivationCompletion => $this->complete($handler, $jobs), $activations);

        $assert($completions, $dispatched);
    }

    public function testTypedSearchAttributesUseMetadataNames(): void
    {
        $commands = $this->start([new Request('UpsertWorkflowTypedSearchAttributes', ['search_attributes' => [
            'Kw' => ['type' => ValueType::Keyword->value, 'operation' => 'set', 'value' => 'v'],
            'Num' => ['type' => ValueType::Float->value, 'operation' => 'set', 'value' => 1.5],
            'Gone' => ['type' => ValueType::Int->value, 'operation' => 'unset'],
        ]])]);

        $fields = $commands[0]->getUpsertWorkflowSearchAttributes()->getSearchAttributes()->getIndexedFields();
        self::assertSame('Keyword', $fields['Kw']->getMetadata()['type']);
        self::assertSame('Double', $fields['Num']->getMetadata()['type']);
        self::assertFalse(isset($fields['Gone']->getMetadata()['type']));
    }

    public function testUnknownSearchAttributeTypeFailsTheActivation(): void
    {
        $completion = $this->complete($this->activations(static fn(array $messages): array => $messages[0] instanceof ServerRequest
            ? [new Request('UpsertWorkflowTypedSearchAttributes', ['search_attributes' => [
                'Bad' => ['type' => 'decimal', 'operation' => 'set', 'value' => 1],
            ]])]
            : []), [self::initialize()]);

        self::assertSame('failed', $completion->getStatus());
        self::assertStringContainsString('"decimal"', $completion->getFailed()->getFailure()->getMessage());
    }

    public function testNegativeTimerFailsLikeRoadRunner(): void
    {
        $responses = [];
        $this->complete($this->activations(static function (array $messages) use (&$responses): array {
            if ($messages[0] instanceof ServerRequest) {
                return [new Request('NewTimer', ['ms' => -5])];
            }
            \array_push($responses, ...$messages);
            return [];
        }), [self::initialize()]);

        self::assertCount(1, $responses);
        self::assertInstanceOf(FailureResponse::class, $responses[0]);
        self::assertInstanceOf(ApplicationFailure::class, $responses[0]->getFailure());
        self::assertSame('negative duration provided -5ms', $responses[0]->getFailure()->getOriginalMessage());
    }

    public function testZeroTimerResolvesWithoutCommand(): void
    {
        $responses = [];
        $completion = $this->complete($this->activations(static function (array $messages) use (&$responses): array {
            if ($messages[0] instanceof ServerRequest) {
                return [new Request('NewTimer', ['ms' => 0])];
            }
            \array_push($responses, ...$messages);
            return [];
        }), [self::initialize()]);

        self::assertCount(0, $completion->getSuccessful()->getCommands());
        self::assertInstanceOf(SuccessResponse::class, $responses[0]);
    }

    public function testLocalActivityWaitsForCancellationCompleted(): void
    {
        $commands = $this->start([new Request('ExecuteLocalActivity', ['name' => 'Act', 'options' => []])]);

        self::assertSame(
            ActivityCancellationType::WAIT_CANCELLATION_COMPLETED,
            $commands[0]->getScheduleLocalActivity()->getCancellationType(),
        );
    }

    public static function provideChildWaitForCancellation(): iterable
    {
        yield 'default' => [[]];
        yield 'try cancel' => [['WaitForCancellation' => false]];
        yield 'wait for cancellation' => [['WaitForCancellation' => true]];
    }

    #[DataProvider('provideChildWaitForCancellation')]
    public function testChildWaitsForCancellationCompletedLikeRoadRunner(array $options): void
    {
        $commands = $this->start([new Request('ExecuteChildWorkflow', ['name' => 'Child', 'options' => $options])]);

        self::assertSame(
            ChildWorkflowCancellationType::WAIT_CANCELLATION_COMPLETED,
            $commands[0]->getStartChildWorkflowExecution()->getCancellationType(),
        );
    }

    public function testChildStartFailureAnswersWaitersAndReleasesTheChild(): void
    {
        $run = new RunState('run');
        $seq = $run->bind(10, RunState::CHILD);
        $run->startChild(10, 'child-id');
        $run->awaitChildExecution(10, 11);

        $messages = $this->resolutions()->childStarted($run, new ResolveChildWorkflowExecutionStart([
            'seq' => $seq,
            'failed' => new ResolveChildWorkflowExecutionStartFailure(['workflow_id' => 'child-id', 'workflow_type' => 'Child']),
        ]), new TickInfo(new \DateTimeImmutable()));

        self::assertSame([10, 11], \array_map(static fn(CommandInterface $message): int => $message->getID(), $messages));
        self::assertInstanceOf(ChildWorkflowFailure::class, $messages[0]->getFailure());
        self::assertSame([[], [], []], $this->childState($run));
    }

    public function testChildResultReleasesTheStartedChild(): void
    {
        $run = new RunState('run');
        $seq = $run->bind(10, RunState::CHILD);
        $run->startChild(10, 'child-id');
        $resolutions = $this->resolutions();
        $tick = new TickInfo(new \DateTimeImmutable());

        $resolutions->childStarted($run, new ResolveChildWorkflowExecutionStart([
            'seq' => $seq,
            'succeeded' => new ResolveChildWorkflowExecutionStartSuccess(['run_id' => 'child-run']),
        ]), $tick);
        self::assertSame(['child-id', 'child-run'], $run->childExecution(10));

        $resolutions->childResult($run, new ResolveChildWorkflowExecution([
            'seq' => $seq,
            'result' => new ChildWorkflowResult(['completed' => new ChildSuccess()]),
        ]), $tick);

        self::assertSame([[], [], []], $this->childState($run));
    }

    private static function payload(string $value): Payload
    {
        return DataConverter::createDefault()->toPayload($value);
    }

    private static function job(string $variant, object $job): WorkflowActivationJob
    {
        return new WorkflowActivationJob([$variant => $job]);
    }

    private static function resolveActivity(ActivityResolution $result): WorkflowActivationJob
    {
        return self::job('resolve_activity', new ResolveActivity(['seq' => 1, 'result' => $result]));
    }

    private static function applicationFailure(string $message): Failure
    {
        return new Failure(['message' => $message, 'application_failure_info' => new ApplicationFailureInfo()]);
    }

    private static function canceledFailure(): Failure
    {
        return new Failure(['message' => 'canceled', 'canceled_failure_info' => new CanceledFailureInfo()]);
    }

    private static function cancelledActivity(): ActivityResolution
    {
        return new ActivityResolution(['cancelled' => new ActivityCancellation(['failure' => new Failure([
            'message' => 'activity canceled',
            'activity_failure_info' => new ActivityFailureInfo(['activity_type' => new ActivityType(['name' => 'Act'])]),
            'cause' => self::canceledFailure(),
        ])])]);
    }

    private static function childStarted(): WorkflowActivationJob
    {
        return self::job('resolve_child_workflow_execution_start', new ResolveChildWorkflowExecutionStart([
            'seq' => 1,
            'succeeded' => new ResolveChildWorkflowExecutionStartSuccess(['run_id' => 'child-run']),
        ]));
    }

    private static function childResult(ChildWorkflowResult $result): WorkflowActivationJob
    {
        return self::job('resolve_child_workflow_execution', new ResolveChildWorkflowExecution(['seq' => 1, 'result' => $result]));
    }

    private static function query(): WorkflowActivationJob
    {
        return self::job('query_workflow', new QueryWorkflow(['query_id' => 'q1', 'query_type' => 'state']));
    }

    private static function queryResult(WorkflowActivationCompletion $completion): QueryResult
    {
        return $completion->getSuccessful()->getCommands()[0]->getRespondToQuery();
    }

    private static function onStart(\Closure $commands): \Closure
    {
        return static fn(array $messages): array => $messages[0] instanceof ServerRequest && $messages[0]->getName() === 'StartWorkflow' ? $commands() : [];
    }

    private static function onQuery(\Closure $responses): \Closure
    {
        return static fn(array $messages): array => $messages[0] instanceof ServerRequest && $messages[0]->getName() === 'InvokeQuery' ? $responses($messages[0]->getID()) : [];
    }

    private static function initialize(): WorkflowActivationJob
    {
        return new WorkflowActivationJob(['initialize_workflow' => new InitializeWorkflow([
            'workflow_type' => 'Wf',
            'workflow_id' => 'wf-id',
            'attempt' => 1,
        ])]);
    }

    /**
     * @param list<CommandInterface> $commands
     * @return list<WorkflowCommand>
     */
    private function start(array $commands): array
    {
        $completion = $this->complete($this->activations(
            static fn(array $messages): array => $messages[0] instanceof ServerRequest ? $commands : [],
        ), [self::initialize()]);
        self::assertSame('successful', $completion->getStatus(), $completion->serializeToJsonString());

        return \iterator_to_array($completion->getSuccessful()->getCommands());
    }

    /**
     * @param list<WorkflowActivationJob> $jobs
     */
    private function complete(WorkflowActivations $activations, array $jobs): WorkflowActivationCompletion
    {
        $activation = new WorkflowActivation(['run_id' => 'run-id', 'jobs' => $jobs]);
        $completion = new WorkflowActivationCompletion();
        $completion->mergeFromString($activations->handle($activation->serializeToString()));

        return $completion;
    }

    private function activations(\Closure $dispatch): WorkflowActivations
    {
        return new WorkflowActivations(DataConverter::createDefault(), $dispatch, 'ns', 'queue', [], false);
    }

    private function resolutions(): ResolutionMapper
    {
        return new ResolutionMapper(new PayloadMapper(DataConverter::createDefault()), 'ns');
    }

    private function childState(RunState $run): array
    {
        return (fn(): array => [$this->childWorkflowIds, $this->childExecutions, $this->childWaiters])->call($run);
    }
}
