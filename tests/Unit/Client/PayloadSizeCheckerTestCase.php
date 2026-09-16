<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Temporal\Api\Batch\V1\BatchOperationSignal;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\CanceledFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Failure\V1\ResetWorkflowFailureInfo;
use Temporal\Api\Failure\V1\TimeoutFailureInfo;
use Temporal\Api\Query\V1\WorkflowQuery;
use Temporal\Api\Schedule\V1\Schedule;
use Temporal\Api\Schedule\V1\ScheduleAction;
use Temporal\Api\Update\V1\Input;
use Temporal\Api\Update\V1\Request as UpdateRequest;
use Temporal\Api\Workflow\V1\NewWorkflowExecutionInfo;
use Temporal\Api\Workflow\V1\PostResetOperation;
use Temporal\Api\Workflow\V1\PostResetOperation\SignalWorkflow;
use Temporal\Api\Workflowservice\V1\CreateScheduleRequest;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest\Operation;
use Temporal\Api\Workflowservice\V1\QueryWorkflowRequest;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdRequest;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdRequest;
use Temporal\Api\Workflowservice\V1\ResetWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\StartBatchOperationRequest;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\UpdateScheduleRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionRequest;
use Temporal\Common\PayloadLimitOptions;
use Temporal\Internal\Client\PayloadSizeChecker;
use Temporal\Tests\Unit\Client\Stub\LoggerSpy;

final class PayloadSizeCheckerTestCase extends TestCase
{
    private LoggerSpy $logger;

    /**
     * @return iterable<string, array{\Closure(): object, non-empty-string, list<string>}>
     */
    public static function oversizedRequests(): iterable
    {
        yield 'StartWorkflowExecution: input' => [
            static fn() => (new StartWorkflowExecutionRequest())->setInput(self::payloads(2000)),
            'StartWorkflowExecution',
            ['payloads'],
        ];

        yield 'StartWorkflowExecution: last completion result' => [
            static fn() => (new StartWorkflowExecutionRequest())->setLastCompletionResult(self::payloads(2000)),
            'StartWorkflowExecution',
            ['payloads'],
        ];

        yield 'StartWorkflowExecution: memo' => [
            static fn() => (new StartWorkflowExecutionRequest())->setMemo(self::memo(2000)),
            'StartWorkflowExecution',
            ['memo'],
        ];

        yield 'SignalWithStartWorkflowExecution: input and signal input' => [
            static fn() => (new SignalWithStartWorkflowExecutionRequest())
                ->setInput(self::payloads(2000))
                ->setSignalInput(self::payloads(3000)),
            'SignalWithStartWorkflowExecution',
            ['payloads', 'payloads'],
        ];

        yield 'ExecuteMultiOperation: nested start and update' => [
            static fn() => (new ExecuteMultiOperationRequest())->setOperations([
                (new Operation())->setStartWorkflow(
                    (new StartWorkflowExecutionRequest())->setInput(self::payloads(2000)),
                ),
                (new Operation())->setUpdateWorkflow(self::updateRequest(3000)),
            ]),
            'ExecuteMultiOperation',
            ['payloads', 'payloads'],
        ];

        yield 'SignalWorkflowExecution' => [
            static fn() => (new SignalWorkflowExecutionRequest())->setInput(self::payloads(2000)),
            'SignalWorkflowExecution',
            ['payloads'],
        ];

        yield 'UpdateWorkflowExecution' => [
            static fn() => self::updateRequest(2000),
            'UpdateWorkflowExecution',
            ['payloads'],
        ];

        yield 'QueryWorkflow' => [
            static fn() => (new QueryWorkflowRequest())->setQuery(
                (new WorkflowQuery())->setQueryArgs(self::payloads(2000)),
            ),
            'QueryWorkflow',
            ['payloads'],
        ];

        yield 'RespondActivityTaskCompleted' => [
            static fn() => (new RespondActivityTaskCompletedRequest())->setResult(self::payloads(2000)),
            'RespondActivityTaskCompleted',
            ['payloads'],
        ];

        yield 'RespondActivityTaskCompletedById' => [
            static fn() => (new RespondActivityTaskCompletedByIdRequest())->setResult(self::payloads(2000)),
            'RespondActivityTaskCompletedById',
            ['payloads'],
        ];

        yield 'RespondActivityTaskFailed: application failure details' => [
            static fn() => (new RespondActivityTaskFailedRequest())->setFailure(
                (new Failure())->setApplicationFailureInfo(
                    (new ApplicationFailureInfo())->setDetails(self::payloads(2000)),
                ),
            ),
            'RespondActivityTaskFailed',
            ['payloads'],
        ];

        yield 'RespondActivityTaskFailed: canceled failure details' => [
            static fn() => (new RespondActivityTaskFailedRequest())->setFailure(
                (new Failure())->setCanceledFailureInfo(
                    (new CanceledFailureInfo())->setDetails(self::payloads(2000)),
                ),
            ),
            'RespondActivityTaskFailed',
            ['payloads'],
        ];

        yield 'RespondActivityTaskFailed: timeout heartbeat details' => [
            static fn() => (new RespondActivityTaskFailedRequest())->setFailure(
                (new Failure())->setTimeoutFailureInfo(
                    (new TimeoutFailureInfo())->setLastHeartbeatDetails(self::payloads(2000)),
                ),
            ),
            'RespondActivityTaskFailed',
            ['payloads'],
        ];

        yield 'RespondActivityTaskFailed: reset workflow heartbeat details' => [
            static fn() => (new RespondActivityTaskFailedRequest())->setFailure(
                (new Failure())->setResetWorkflowFailureInfo(
                    (new ResetWorkflowFailureInfo())->setLastHeartbeatDetails(self::payloads(2000)),
                ),
            ),
            'RespondActivityTaskFailed',
            ['payloads'],
        ];

        yield 'RespondActivityTaskFailedById: last heartbeat details' => [
            static fn() => (new RespondActivityTaskFailedByIdRequest())
                ->setLastHeartbeatDetails(self::payloads(2000)),
            'RespondActivityTaskFailedById',
            ['payloads'],
        ];

        yield 'RespondActivityTaskCanceled' => [
            static fn() => (new RespondActivityTaskCanceledRequest())->setDetails(self::payloads(2000)),
            'RespondActivityTaskCanceled',
            ['payloads'],
        ];

        yield 'RespondActivityTaskCanceledById' => [
            static fn() => (new RespondActivityTaskCanceledByIdRequest())->setDetails(self::payloads(2000)),
            'RespondActivityTaskCanceledById',
            ['payloads'],
        ];

        yield 'RecordActivityTaskHeartbeat' => [
            static fn() => (new RecordActivityTaskHeartbeatRequest())->setDetails(self::payloads(2000)),
            'RecordActivityTaskHeartbeat',
            ['payloads'],
        ];

        yield 'RecordActivityTaskHeartbeatById' => [
            static fn() => (new RecordActivityTaskHeartbeatByIdRequest())->setDetails(self::payloads(2000)),
            'RecordActivityTaskHeartbeatById',
            ['payloads'],
        ];

        yield 'TerminateWorkflowExecution' => [
            static fn() => (new TerminateWorkflowExecutionRequest())->setDetails(self::payloads(2000)),
            'TerminateWorkflowExecution',
            ['payloads'],
        ];

        yield 'CreateSchedule: request memo and workflow input as one value' => [
            static fn() => (new CreateScheduleRequest())
                ->setMemo(self::memo(600))
                ->setSchedule(self::schedule(600, 0)),
            'CreateSchedule',
            ['payloads'],
        ];

        yield 'CreateSchedule: the memo of the action is not measured on its own' => [
            static fn() => (new CreateScheduleRequest())->setSchedule(self::schedule(0, 2000)),
            'CreateSchedule',
            [],
        ];

        yield 'StartBatchOperation: signal input' => [
            static fn() => (new StartBatchOperationRequest())->setSignalOperation(
                (new BatchOperationSignal())->setInput(self::payloads(2000)),
            ),
            'StartBatchOperation',
            ['payloads'],
        ];

        yield 'ResetWorkflowExecution: post reset signal input' => [
            static fn() => (new ResetWorkflowExecutionRequest())->setPostResetOperations([
                (new PostResetOperation())->setSignalWorkflow(
                    (new SignalWorkflow())->setInput(self::payloads(2000)),
                ),
                (new PostResetOperation())->setSignalWorkflow(
                    (new SignalWorkflow())->setInput(self::payloads(3000)),
                ),
            ]),
            'ResetWorkflowExecution',
            ['payloads', 'payloads'],
        ];

        yield 'UpdateSchedule: workflow input' => [
            static fn() => (new UpdateScheduleRequest())->setSchedule(self::schedule(2000, 0)),
            'UpdateSchedule',
            ['payloads'],
        ];

        yield 'UpdateSchedule: schedule memo' => [
            static fn() => (new UpdateScheduleRequest())->setSchedule(self::schedule(0, 2000)),
            'UpdateSchedule',
            ['memo'],
        ];
    }

    /**
     * @param \Closure(): object $request
     * @param non-empty-string $method
     * @param list<string> $expected Kind of every expected warning, in order.
     */
    #[DataProvider('oversizedRequests')]
    public function testWarnsForEveryOversizedField(\Closure $request, string $method, array $expected): void
    {
        $this->check($request(), $method);

        self::assertSame($expected, \array_column($this->logger->records, 'kind'));
        foreach ($this->logger->records as $record) {
            self::assertSame(LogLevel::WARNING, $record['level']);
            self::assertStringContainsString('[TMPRL1103]', $record['message']);
            self::assertSame($method, $record['context']['method']);
            self::assertSame(1024, $record['context']['limit']);
        }
    }

    public function testExactlyTheLimitIsNotReported(): void
    {
        $payloads = self::payloads(100);
        $size = \strlen($payloads->serializeToString());

        $this->check(
            (new StartWorkflowExecutionRequest())->setInput($payloads),
            'StartWorkflowExecution',
            new PayloadLimitOptions($size, $size),
        );

        self::assertSame([], $this->logger->records);
    }

    public function testKeepsSilentBelowTheLimit(): void
    {
        $this->check((new StartWorkflowExecutionRequest())->setInput(self::payloads(100)), 'StartWorkflowExecution');

        self::assertSame([], $this->logger->records);
    }

    public function testMemoUsesItsOwnLimit(): void
    {
        $this->check(
            (new StartWorkflowExecutionRequest())->setMemo(self::memo(2000)),
            'StartWorkflowExecution',
            new PayloadLimitOptions(1024 * 1024, 1024),
        );

        self::assertSame(['memo'], \array_column($this->logger->records, 'kind'));
    }

    public function testCreateScheduleDoesNotMeasureTheActionMemoOnItsOwn(): void
    {
        $this->check(
            (new CreateScheduleRequest())->setMemo(self::memo(100))->setSchedule(self::schedule(10, 900)),
            'CreateSchedule',
            new PayloadLimitOptions(1024, 512),
        );

        self::assertSame([], \array_column($this->logger->records, 'kind'));
    }

    public function testMeasuresEveryFailureOfTheChain(): void
    {
        $request = (new RespondActivityTaskFailedRequest())->setFailure(
            self::failure(2000)->setCause(self::failure(3000)),
        );

        $this->check($request, 'RespondActivityTaskFailed');

        self::assertCount(2, $this->logger->records);
    }

    public function testFailureChainIsBounded(): void
    {
        $failure = self::failure(2000);
        for ($i = 0; $i < 30; ++$i) {
            $failure = self::failure(2000)->setCause($failure);
        }

        $this->check((new RespondActivityTaskFailedRequest())->setFailure($failure), 'RespondActivityTaskFailed');

        self::assertCount(20, $this->logger->records);
    }

    public function testTheReportedSizeIsTheWireSize(): void
    {
        $payloads = self::payloads(2000);

        $this->check((new StartWorkflowExecutionRequest())->setInput($payloads), 'StartWorkflowExecution');

        self::assertSame(\strlen($payloads->serializeToString()), $this->logger->records[0]['context']['size']);
    }

    public function testSearchAttributesAreNotMeasured(): void
    {
        $request = (new StartWorkflowExecutionRequest())
            ->setSearchAttributes(new SearchAttributes([
                'indexed_fields' => ['attr' => self::payload(2000)],
            ]));

        $this->check($request, 'StartWorkflowExecution');

        self::assertSame([], $this->logger->records);
    }

    public function testDisabledLimitsProduceNoWarning(): void
    {
        $request = (new StartWorkflowExecutionRequest())->setInput(self::payloads(1024 * 1024));

        $this->check($request, 'StartWorkflowExecution', PayloadLimitOptions::disabled());

        self::assertSame([], $this->logger->records);
    }

    public function testOnlyTheMemoWarningCanBeDisabled(): void
    {
        $request = (new StartWorkflowExecutionRequest())
            ->setInput(self::payloads(2000))
            ->setMemo(self::memo(2000));

        $this->check($request, 'StartWorkflowExecution', new PayloadLimitOptions(1024, null));

        self::assertSame(['payloads'], \array_column($this->logger->records, 'kind'));
    }

    public function testFailureOfTheCheckDoesNotBreakTheCall(): void
    {
        $logger = new class extends AbstractLogger {
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                throw new \RuntimeException('Broken logger');
            }
        };

        $checker = new PayloadSizeChecker(new PayloadLimitOptions(1024, 1024), $logger);

        $checker->check(
            'StartWorkflowExecution',
            (new StartWorkflowExecutionRequest())->setInput(self::payloads(2000)),
        );

        self::assertTrue(true);
    }

    public function testNonProtobufRequestIsIgnored(): void
    {
        $this->check(new \stdClass(), 'SomeCall');

        self::assertSame([], $this->logger->records);
    }

    protected function setUp(): void
    {
        $this->logger = new LoggerSpy();
        parent::setUp();
    }

    private static function payload(int $size): Payload
    {
        return (new Payload())->setData(\str_repeat('x', $size));
    }

    private static function payloads(int $size): Payloads
    {
        return new Payloads(['payloads' => [self::payload($size)]]);
    }

    private static function memo(int $size): Memo
    {
        return new Memo(['fields' => ['key' => self::payload($size)]]);
    }

    private static function failure(int $size): Failure
    {
        return (new Failure())->setApplicationFailureInfo(
            (new ApplicationFailureInfo())->setDetails(self::payloads($size)),
        );
    }

    private static function updateRequest(int $size): UpdateWorkflowExecutionRequest
    {
        return (new UpdateWorkflowExecutionRequest())->setRequest(
            (new UpdateRequest())->setInput((new Input())->setArgs(self::payloads($size))),
        );
    }

    private static function schedule(int $inputSize, int $memoSize): Schedule
    {
        $action = new NewWorkflowExecutionInfo();
        if ($inputSize > 0) {
            $action->setInput(self::payloads($inputSize));
        }

        if ($memoSize > 0) {
            $action->setMemo(self::memo($memoSize));
        }

        return (new Schedule())->setAction((new ScheduleAction())->setStartWorkflow($action));
    }

    private function check(object $request, string $method, ?PayloadLimitOptions $options = null): void
    {
        $checker = new PayloadSizeChecker($options ?? new PayloadLimitOptions(1024, 1024), $this->logger);
        $checker->check($method, $request);
    }
}
