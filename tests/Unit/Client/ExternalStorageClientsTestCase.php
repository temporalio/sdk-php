<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Enums\V1\UpdateWorkflowExecutionLifecycleStage;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\WorkflowExecutionCompletedEventAttributes;
use Temporal\Api\History\V1\WorkflowExecutionFailedEventAttributes;
use Temporal\Api\Update\V1\Outcome;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Api\Workflowservice\V1\QueryWorkflowResponse;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\ExternalStorage\ExternalStorage;
use Temporal\DataConverter\ExternalStorage\FileStorageDriver;
use Temporal\DataConverter\ExternalStorage\InMemoryStorageDriver;
use Temporal\DataConverter\ExternalStorage\StorageDriverInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Internal\ExternalStorage\ExternalStorageRunner;
use Temporal\Internal\ExternalStorage\ReferencePayload;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Api\Activity\V1\ActivityExecutionOutcome;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Schedule\V1\Schedule as ScheduleMessage;
use Temporal\Api\Schedule\V1\ScheduleAction;
use Temporal\Api\TaskQueue\V1\TaskQueue;
use Temporal\Api\Update\V1\UpdateRef;
use Temporal\Api\Workflow\V1\NewWorkflowExecutionInfo;
use Temporal\Api\Workflowservice\V1\DescribeScheduleResponse;
use Temporal\Api\Workflowservice\V1\PollActivityExecutionResponse;
use Temporal\Api\Workflowservice\V1\StartActivityExecutionResponse;
use Temporal\Client\Activity\StartActivityOptions;
use Temporal\Client\ActivityClient;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\ScheduleClient;
use Temporal\DataConverter\EncodedValues;
use Temporal\Workflow\WorkflowExecution;

final class ExternalStorageClientsTestCase extends TestCase
{
    private const THRESHOLD = 1024;

    private string $directory;

    /** @var array<string, list<object>> */
    private array $requests = [];

    /** @var array<string, \Closure(object): object> */
    private array $responses = [];

    public static function provideDrivers(): iterable
    {
        yield 'in-memory' => [static fn(string $directory): StorageDriverInterface => new InMemoryStorageDriver()];
        yield 'file' => [static fn(string $directory): StorageDriverInterface => new FileStorageDriver($directory)];
    }

    #[DataProvider('provideDrivers')]
    public function testStartStoresTheInputAndTheMemo(\Closure $driver): void
    {
        $client = $this->workflowClient($driver($this->directory));
        $this->responses['StartWorkflowExecution'] = static fn(): object => (new StartWorkflowExecutionResponse())->setRunId('run-id');

        $client->start(
            $client->newUntypedWorkflowStub('Wf', WorkflowOptions::new()->withTaskQueue('q')->withMemo(['memo' => self::big('m')])),
            self::big('input'),
        );

        $request = $this->requests['StartWorkflowExecution'][0];
        self::assertTrue(ReferencePayload::isReference($request->getInput()->getPayloads()[0]));
        self::assertTrue(ReferencePayload::isReference($request->getMemo()->getFields()['memo']));
    }

    #[DataProvider('provideDrivers')]
    public function testSignalWithStartStoresTheInputAndTheSignal(\Closure $driver): void
    {
        $client = $this->workflowClient($driver($this->directory));
        $this->responses['SignalWithStartWorkflowExecution'] = static fn(): object => (new SignalWithStartWorkflowExecutionResponse())->setRunId('run-id');

        $client->startWithSignal(
            $client->newUntypedWorkflowStub('Wf', WorkflowOptions::new()->withTaskQueue('q')),
            'signal',
            [self::big('signal')],
            [self::big('input')],
        );

        $request = $this->requests['SignalWithStartWorkflowExecution'][0];
        self::assertTrue(ReferencePayload::isReference($request->getInput()->getPayloads()[0]));
        self::assertTrue(ReferencePayload::isReference($request->getSignalInput()->getPayloads()[0]));
    }

    #[DataProvider('provideDrivers')]
    public function testSignalAndTerminateStoreTheirPayloads(\Closure $driver): void
    {
        $client = $this->workflowClient($driver($this->directory));
        $stub = $client->newUntypedRunningWorkflowStub('wf-id', 'run-id', 'Wf');

        $stub->signal('signal', self::big('signal'));
        $stub->terminate('reason', [self::big('details')]);

        self::assertTrue(ReferencePayload::isReference($this->requests['SignalWorkflowExecution'][0]->getInput()->getPayloads()[0]));
        self::assertTrue(ReferencePayload::isReference($this->requests['TerminateWorkflowExecution'][0]->getDetails()->getPayloads()[0]));
    }

    #[DataProvider('provideDrivers')]
    public function testQueryStoresTheArgumentsAndRetrievesTheResult(\Closure $driver): void
    {
        $storage = $driver($this->directory);
        $client = $this->workflowClient($storage);
        $result = $this->reference($storage, self::big('result'));
        $this->responses['QueryWorkflow'] = static fn(): object => (new QueryWorkflowResponse())
            ->setQueryResult(new Payloads(['payloads' => [$result]]));

        $value = $client->newUntypedRunningWorkflowStub('wf-id', 'run-id', 'Wf')
            ->query('query', self::big('argument'))
            ?->getValue(0, 'string');

        self::assertTrue(ReferencePayload::isReference($this->requests['QueryWorkflow'][0]->getQuery()->getQueryArgs()->getPayloads()[0]));
        self::assertSame(self::big('result'), $value);
    }

    #[DataProvider('provideDrivers')]
    public function testUpdateStoresTheArgumentsAndRetrievesTheResult(\Closure $driver): void
    {
        $storage = $driver($this->directory);
        $client = $this->workflowClient($storage);
        $result = $this->reference($storage, self::big('result'));
        $this->responses['UpdateWorkflowExecution'] = static fn(object $request): object => (new UpdateWorkflowExecutionResponse())
            ->setStage(UpdateWorkflowExecutionLifecycleStage::UPDATE_WORKFLOW_EXECUTION_LIFECYCLE_STAGE_COMPLETED)
            ->setUpdateRef(new UpdateRef([
                'workflow_execution' => $request->getWorkflowExecution(),
                'update_id' => $request->getRequest()->getMeta()->getUpdateId(),
            ]))
            ->setOutcome(new Outcome(['success' => new Payloads(['payloads' => [$result]])]));

        $value = $client->newUntypedRunningWorkflowStub('wf-id', 'run-id', 'Wf')
            ->update('update', self::big('argument'))
            ?->getValue(0, 'string');

        self::assertTrue(ReferencePayload::isReference(
            $this->requests['UpdateWorkflowExecution'][0]->getRequest()->getInput()->getArgs()->getPayloads()[0],
        ));
        self::assertSame(self::big('result'), $value);
    }

    #[DataProvider('provideDrivers')]
    public function testGetResultRetrievesTheResult(\Closure $driver): void
    {
        $storage = $driver($this->directory);
        $client = $this->workflowClient($storage);
        $result = $this->reference($storage, self::big('result'));
        $this->responses['GetWorkflowExecutionHistory'] = static fn(): object => self::history(
            (new HistoryEvent())
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED)
                ->setWorkflowExecutionCompletedEventAttributes(
                    (new WorkflowExecutionCompletedEventAttributes())->setResult(new Payloads(['payloads' => [$result]])),
                ),
        );

        $value = $client->newUntypedRunningWorkflowStub('wf-id', 'run-id', 'Wf')->getResult('string');

        self::assertSame(self::big('result'), $value);
    }

    #[DataProvider('provideDrivers')]
    public function testFailureDetailsAreRetrievedWhenRead(\Closure $driver): void
    {
        $storage = $driver($this->directory);
        $client = $this->workflowClient($storage);
        $details = $this->reference($storage, self::big('details'));
        $this->responses['GetWorkflowExecutionHistory'] = static fn(): object => self::history(
            (new HistoryEvent())
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_FAILED)
                ->setWorkflowExecutionFailedEventAttributes(
                    (new WorkflowExecutionFailedEventAttributes())->setFailure(
                        (new Failure())->setMessage('failed')->setApplicationFailureInfo(
                            (new ApplicationFailureInfo())->setType('Error')->setDetails(new Payloads(['payloads' => [$details]])),
                        ),
                    ),
                ),
        );

        try {
            $client->newUntypedRunningWorkflowStub('wf-id', 'run-id', 'Wf')->getResult('string');
            self::fail('The workflow failure is not reported.');
        } catch (WorkflowFailedException $e) {
            $failure = $e->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $failure);
            self::assertSame(self::big('details'), $failure->getDetails()->getValue(0, 'string'));
        }
    }

    #[DataProvider('provideDrivers')]
    public function testRawHistoryKeepsTheReferences(\Closure $driver): void
    {
        $storage = $driver($this->directory);
        $client = $this->workflowClient($storage);
        $result = $this->reference($storage, self::big('result'));
        $this->responses['GetWorkflowExecutionHistory'] = static fn(): object => self::history(
            (new HistoryEvent())
                ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED)
                ->setWorkflowExecutionCompletedEventAttributes(
                    (new WorkflowExecutionCompletedEventAttributes())->setResult(new Payloads(['payloads' => [$result]])),
                ),
        );

        foreach ($client->getWorkflowHistory(new WorkflowExecution('wf-id', 'run-id')) as $event) {
            $payload = $event->getWorkflowExecutionCompletedEventAttributes()->getResult()->getPayloads()[0];
            self::assertTrue(ReferencePayload::isReference($payload));
        }
    }

    #[DataProvider('provideDrivers')]
    public function testActivityCompletionStoresResultsHeartbeatsAndFailures(\Closure $driver): void
    {
        $completion = $this->workflowClient($driver($this->directory))->newActivityCompletionClient();

        $completion->complete('wf-id', 'run-id', 'act-id', self::big('result'));
        $completion->completeByToken('token', self::big('result'));
        $completion->recordHeartbeat('wf-id', 'run-id', 'act-id', self::big('details'));
        $completion->completeExceptionally(
            'wf-id',
            'run-id',
            'act-id',
            new ApplicationFailure('failed', 'Error', false, EncodedValues::fromValues([self::big('details')])),
        );

        self::assertTrue(ReferencePayload::isReference($this->requests['RespondActivityTaskCompletedById'][0]->getResult()->getPayloads()[0]));
        self::assertTrue(ReferencePayload::isReference($this->requests['RespondActivityTaskCompleted'][0]->getResult()->getPayloads()[0]));
        self::assertTrue(ReferencePayload::isReference($this->requests['RecordActivityTaskHeartbeatById'][0]->getDetails()->getPayloads()[0]));
        self::assertTrue(ReferencePayload::isReference(
            $this->requests['RespondActivityTaskFailedById'][0]->getFailure()->getApplicationFailureInfo()->getDetails()->getPayloads()[0],
        ));
    }

    #[DataProvider('provideDrivers')]
    public function testScheduleStoresTheActionInputAndRetrievesItOnDescribe(\Closure $driver): void
    {
        $storage = $driver($this->directory);
        $client = ScheduleClient::create($this->serviceClient(), $this->clientOptions($storage), logger: new NullLogger());
        $input = $this->reference($storage, self::big('described'));
        $this->responses['DescribeSchedule'] = static fn(): object => new DescribeScheduleResponse([
            'schedule' => new ScheduleMessage([
                'action' => new ScheduleAction([
                    'start_workflow' => new NewWorkflowExecutionInfo([
                        'workflow_id' => 'wf-id',
                        'workflow_type' => new WorkflowType(['name' => 'Wf']),
                        'task_queue' => new TaskQueue(['name' => 'q']),
                        'input' => new Payloads(['payloads' => [$input]]),
                        'header' => new Header(),
                    ]),
                ]),
            ]),
        ]);

        $handle = $client->createSchedule(
            Schedule::new()->withAction(StartWorkflowAction::new('Wf')->withTaskQueue('q')->withInput([self::big('input')])),
            scheduleId: 'schedule-id',
        );
        $action = $handle->describe()->schedule->action;

        self::assertTrue(ReferencePayload::isReference(
            $this->requests['CreateSchedule'][0]->getSchedule()->getAction()->getStartWorkflow()->getInput()->getPayloads()[0],
        ));
        self::assertInstanceOf(StartWorkflowAction::class, $action);
        self::assertSame(self::big('described'), $action->input->getValue(0, 'string'));
    }

    #[DataProvider('provideDrivers')]
    public function testStandaloneActivityStoresTheInputAndRetrievesTheResult(\Closure $driver): void
    {
        $storage = $driver($this->directory);
        $client = ActivityClient::create($this->serviceClient(), $this->clientOptions($storage), logger: new NullLogger());
        $result = $this->reference($storage, self::big('result'));
        $this->responses['StartActivityExecution'] = static fn(): object => (new StartActivityExecutionResponse())->setRunId('run-id');
        $this->responses['PollActivityExecution'] = static fn(): object => (new PollActivityExecutionResponse())
            ->setRunId('run-id')
            ->setOutcome(new ActivityExecutionOutcome(['result' => new Payloads(['payloads' => [$result]])]));

        $handle = $client->start(
            'Act',
            StartActivityOptions::new()->withId('act-id')->withTaskQueue('q')->withStartToCloseTimeout(10),
            self::big('input'),
        );

        self::assertTrue(ReferencePayload::isReference($this->requests['StartActivityExecution'][0]->getInput()->getPayloads()[0]));
        self::assertSame(self::big('result'), $handle->getResult('string'));
    }

    #[DataProvider('provideDrivers')]
    public function testSmallPayloadsStayInline(\Closure $driver): void
    {
        $client = $this->workflowClient($driver($this->directory));

        $client->newUntypedRunningWorkflowStub('wf-id', 'run-id', 'Wf')->signal('signal', 'small');

        self::assertFalse(ReferencePayload::isReference($this->requests['SignalWorkflowExecution'][0]->getInput()->getPayloads()[0]));
    }

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/temporal-external-storage-' . \bin2hex(\random_bytes(6));
        $this->requests = [];
        $this->responses = [];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->directory)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($files as $file) {
                $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
            }
            \rmdir($this->directory);
        }
        parent::tearDown();
    }

    private static function big(string $prefix): string
    {
        return $prefix . \str_repeat('x', 2 * self::THRESHOLD);
    }

    private static function history(HistoryEvent $event): GetWorkflowExecutionHistoryResponse
    {
        return (new GetWorkflowExecutionHistoryResponse())->setHistory(new History(['events' => [$event->setEventId(1)]]));
    }

    private function reference(StorageDriverInterface $driver, string $value): Payload
    {
        $runner = new ExternalStorageRunner($this->options($driver), 'default', new NullLogger());
        $payloads = new Payloads(['payloads' => [DataConverter::createDefault()->toPayload($value)]]);

        return $runner->store($payloads)->getPayloads()[0];
    }

    private function options(StorageDriverInterface $driver): ExternalStorage
    {
        return new ExternalStorage([$driver], payloadSizeThreshold: self::THRESHOLD);
    }

    private function clientOptions(StorageDriverInterface $driver): ClientOptions
    {
        return (new ClientOptions())->withExternalStorage($this->options($driver));
    }

    private function workflowClient(StorageDriverInterface $driver): WorkflowClient
    {
        return new WorkflowClient($this->serviceClient(), $this->clientOptions($driver), logger: new NullLogger());
    }

    private function serviceClient(): ServiceClient
    {
        $stub = static fn() => new class extends WorkflowServiceClient {
            public function __construct() {}

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::Ready->value;
            }

            public function close(): void {}
        };

        $server = new class($this->requests, $this->responses) implements GrpcClientInterceptor {
            public function __construct(
                private array &$requests,
                private array &$responses,
            ) {}

            public function interceptCall(string $method, object $arg, ContextInterface $ctx, callable $next): object
            {
                $this->requests[$method][] = $arg;
                $response = $this->responses[$method] ?? null;
                if ($response !== null) {
                    return $response($arg);
                }

                $class = 'Temporal\\Api\\Workflowservice\\V1\\' . $method . 'Response';

                return new $class();
            }
        };

        return (new ServiceClient($stub))->withInterceptorPipeline(Pipeline::prepare([$server]));
    }
}
