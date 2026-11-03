<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Temporal\Api\Activity\V1\ActivityExecutionInfo;
use Temporal\Api\Activity\V1\ActivityExecutionOutcome;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Workflowservice\V1\DescribeActivityExecutionResponse;
use Temporal\Api\Workflowservice\V1\PollActivityExecutionResponse;
use Temporal\Api\Workflowservice\V1\StartActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartActivityExecutionResponse;
use Temporal\Client\Activity\ActivityHandle;
use Temporal\Client\Activity\StartActivityOptions;
use Temporal\Client\ActivityClient;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Common\ActivityIdConflictPolicy;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use PHPUnit\Framework\MockObject\MockObject;
use Temporal\Api\Enums\V1\RetryState;
use Temporal\Api\Workflowservice\V1\CountActivityExecutionsRequest;
use Temporal\Api\Workflowservice\V1\CountActivityExecutionsResponse;
use Temporal\Api\Workflowservice\V1\DescribeActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\RequestCancelActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\RequestCancelActivityExecutionResponse;
use Temporal\Api\Workflowservice\V1\TerminateActivityExecutionResponse;
use Temporal\Client\Activity\ActivityExecutionDescription;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\StatusCode;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Client\ActivityNotFoundException;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Interceptor\ActivityClient\CancelInput;
use Temporal\Interceptor\ActivityClient\DescribeInput;
use Temporal\Interceptor\ActivityClient\GetResultInput;
use Temporal\Interceptor\ActivityClient\StartInput;
use Temporal\Interceptor\ActivityClient\TerminateInput;
use Temporal\Interceptor\ActivityClientCallsInterceptor;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\ActivityClientCallsInterceptorTrait;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\ClientPluginTrait;
use Temporal\Plugin\PluginRegistry;
use Temporal\Tests\TestCase;

#[CoversClass(ActivityClient::class)]
#[CoversClass(ActivityHandle::class)]
final class ActivityClientTestCase extends TestCase
{
    public static function provideInvalidOptions(): iterable
    {
        $valid = StartActivityOptions::new()
            ->withId('activity-id')
            ->withTaskQueue('queue')
            ->withStartToCloseTimeout(10);

        yield 'no id' => [StartActivityOptions::new()->withTaskQueue('queue')->withStartToCloseTimeout(10)];
        yield 'no task queue' => [StartActivityOptions::new()->withId('activity-id')->withStartToCloseTimeout(10)];
        yield 'no timeouts' => [$valid->withStartToCloseTimeout(0)];
    }

    public function testStartBuildsRequest(): void
    {
        $captured = new \ArrayObject();
        $client = $this->createMock(ServiceClientInterface::class);
        $client->method('withContext')->willReturnSelf();
        $client->expects($this->once())
            ->method('StartActivityExecution')
            ->willReturnCallback(static function (StartActivityExecutionRequest $request) use ($captured) {
                $captured['request'] = $request;
                return (new StartActivityExecutionResponse())->setRunId('run-id');
            });

        $handle = ActivityClient::create($client)->start(
            'type',
            StartActivityOptions::new()
                ->withId('activity-id')
                ->withTaskQueue('queue')
                ->withScheduleToCloseTimeout(30)
                ->withIdConflictPolicy(ActivityIdConflictPolicy::UseExisting)
                ->withStaticSummary('summary'),
            'arg',
            42,
        );

        $request = $captured['request'];
        self::assertSame('activity-id', $request->getActivityId());
        self::assertSame('type', $request->getActivityType()->getName());
        self::assertSame('queue', $request->getTaskQueue()->getName());
        self::assertSame(30, (int) $request->getScheduleToCloseTimeout()->getSeconds());
        self::assertSame(ActivityIdConflictPolicy::UseExisting->value, $request->getIdConflictPolicy());
        self::assertSame(
            'summary',
            DataConverter::createDefault()->fromPayload($request->getUserMetadata()->getSummary(), 'string'),
        );
        self::assertSame(
            ['arg', 42],
            EncodedValues::fromPayloads($request->getInput(), DataConverter::createDefault())->getValues(),
        );
        self::assertSame('activity-id', $handle->getId());
        self::assertSame('run-id', $handle->getRunId());
    }

    #[DataProvider('provideInvalidOptions')]
    public function testStartRejectsInvalidOptions(StartActivityOptions $options): void
    {
        $client = $this->createMock(ServiceClientInterface::class);
        $client->method('withContext')->willReturnSelf();
        $client->expects($this->never())->method('StartActivityExecution');

        $this->expectException(\InvalidArgumentException::class);

        ActivityClient::create($client)->start('type', $options);
    }

    public function testGetResultPollsUntilOutcome(): void
    {
        $client = $this->createMock(ServiceClientInterface::class);
        $client->method('withContext')->willReturnSelf();
        $client->method('DescribeActivityExecution')->willReturn(self::describeResponse());
        $client->expects($this->exactly(2))
            ->method('PollActivityExecution')
            ->willReturnOnConsecutiveCalls(
                new PollActivityExecutionResponse(),
                (new PollActivityExecutionResponse())->setOutcome(
                    (new ActivityExecutionOutcome())->setResult(
                        EncodedValues::fromValues(['done'], DataConverter::createDefault())->toPayloads(),
                    ),
                ),
            );

        $result = ActivityClient::create($client)->getHandle('activity-id', 'run-id')->getResult('string');

        self::assertSame('done', $result);
    }

    public function testGetResultThrowsOnFailure(): void
    {
        $client = $this->createMock(ServiceClientInterface::class);
        $client->method('withContext')->willReturnSelf();
        $client->method('DescribeActivityExecution')->willReturn(self::describeResponse());
        $client->method('PollActivityExecution')->willReturn(
            (new PollActivityExecutionResponse())->setRunId('run-id')->setOutcome(
                (new ActivityExecutionOutcome())->setRetryState(RetryState::RETRY_STATE_MAXIMUM_ATTEMPTS_REACHED)->setFailure(
                    (new Failure())
                        ->setMessage('boom')
                        ->setApplicationFailureInfo((new ApplicationFailureInfo())->setType('TestError')),
                ),
            ),
        );

        try {
            ActivityClient::create($client)->getHandle('activity-id', 'run-id')->getResult();
            self::fail('Expected ActivityFailedException');
        } catch (ActivityFailedException $e) {
            self::assertSame('activity-id', $e->activityId);
            self::assertSame('run-id', $e->runId);
            self::assertSame(RetryState::RETRY_STATE_MAXIMUM_ATTEMPTS_REACHED, $e->retryState);
            self::assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            self::assertSame('TestError', $e->getPrevious()->getType());
        }
    }

    public function testStartSendsInterceptorHeaderAndCallsInterceptors(): void
    {
        $captured = new \ArrayObject();
        $client = $this->mockClient();
        $client->method('StartActivityExecution')->willReturnCallback(
            static function (StartActivityExecutionRequest $request) use ($captured) {
                $captured['start'] = $request;
                return (new StartActivityExecutionResponse())->setRunId('run-id');
            },
        );
        $client->method('PollActivityExecution')->willReturn(self::resultResponse('run-id', ['done']));
        $client->method('DescribeActivityExecution')->willReturn(self::describeResponse());
        $client->method('RequestCancelActivityExecution')->willReturn(new RequestCancelActivityExecutionResponse());
        $client->method('TerminateActivityExecution')->willReturn(new TerminateActivityExecutionResponse());
        $interceptor = new RecordingInterceptor();

        $handle = ActivityClient::create($client, interceptorProvider: new SimplePipelineProvider([$interceptor]))
            ->start('type', self::validOptions());
        $handle->getResult();
        $handle->describe();
        $handle->cancel('stop');
        $handle->terminate('stop');

        self::assertSame(['start', 'getResult', 'describe', 'cancel', 'terminate'], $interceptor->calls);
        $header = $captured['start']->getHeader();
        self::assertNotNull($header);
        self::assertSame(
            'traced',
            DataConverter::createDefault()->fromPayload($header->getFields()['trace'], 'string'),
        );
    }

    public function testClientPluginConfiguresOptionsAndInterceptors(): void
    {
        $captured = new \ArrayObject();
        $client = $this->mockClient();
        $client->method('StartActivityExecution')->willReturnCallback(
            static function (StartActivityExecutionRequest $request) use ($captured) {
                $captured['start'] = $request;
                return (new StartActivityExecutionResponse())->setRunId('run-id');
            },
        );
        $interceptor = new RecordingInterceptor();
        $plugin = new class($interceptor) implements ClientPluginInterface {
            use ClientPluginTrait;

            public function __construct(private readonly RecordingInterceptor $interceptor) {}

            public function getName(): string
            {
                return 'test.activity-client';
            }

            public function configureClient(ClientPluginContext $context, callable $next): void
            {
                $context->setClientOptions((new ClientOptions())->withNamespace('plugin-namespace'));
                $context->addInterceptor($this->interceptor);
                $next($context);
            }
        };

        ActivityClient::create($client, pluginRegistry: new PluginRegistry([$plugin]))
            ->start('type', self::validOptions());

        self::assertSame('plugin-namespace', $captured['start']->getNamespace());
        self::assertSame(['start'], $interceptor->calls);
    }

    public function testStartTurnsEmptyRunIdIntoNull(): void
    {
        $client = $this->mockClient();
        $client->method('StartActivityExecution')->willReturn(new StartActivityExecutionResponse());

        $handle = ActivityClient::create($client)->start('type', self::validOptions());

        self::assertNull($handle->getRunId());
    }

    public function testDescribeRequestsHeartbeatDetailsAndLastFailure(): void
    {
        $captured = new \ArrayObject();
        $client = $this->mockClient();
        $client->method('DescribeActivityExecution')->willReturnCallback(
            static function (DescribeActivityExecutionRequest $request) use ($captured) {
                $captured['describe'] = $request;
                return self::describeResponse();
            },
        );

        ActivityClient::create($client)->getHandle('activity-id', 'run-id')->describe();

        self::assertTrue($captured['describe']->getIncludeHeartbeatDetails());
        self::assertTrue($captured['describe']->getIncludeLastFailure());
    }

    public function testGetResultReturnsNullForEmptyPayloads(): void
    {
        $client = $this->mockClient();
        $client->method('DescribeActivityExecution')->willReturn(self::describeResponse());
        $client->method('PollActivityExecution')->willReturn(self::resultResponse('run-id', []));

        self::assertNull(ActivityClient::create($client)->getHandle('activity-id', 'run-id')->getResult('string'));
    }

    public function testGetResultPollsAgainAfterPollTimeout(): void
    {
        $client = $this->mockClient();
        $client->method('DescribeActivityExecution')->willReturn(self::describeResponse());
        $client->expects($this->exactly(2))
            ->method('PollActivityExecution')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new TimeoutException('poll timeout')),
                self::resultResponse('run-id', ['done']),
            );

        self::assertSame('done', ActivityClient::create($client)->getHandle('activity-id')->getResult('string'));
    }

    public function testSerializationContextUsesPolledRunId(): void
    {
        $captured = new \ArrayObject();
        $client = $this->mockClient();
        $client->method('PollActivityExecution')->willReturn(self::resultResponse('run-2', ['done']));
        $client->method('DescribeActivityExecution')->willReturnCallback(
            static function (DescribeActivityExecutionRequest $request) use ($captured) {
                $captured['describe'] = $request;
                return self::describeResponse();
            },
        );

        ActivityClient::create($client)->getHandle('activity-id')->getResult();

        self::assertSame('run-2', $captured['describe']->getRunId());
    }

    public function testNotFoundBecomesActivityNotFoundException(): void
    {
        $client = $this->mockClient();
        $client->method('DescribeActivityExecution')->willThrowException(
            new ServiceClientException((object) ['code' => StatusCode::NOT_FOUND, 'details' => 'not found', 'metadata' => []]),
        );

        try {
            ActivityClient::create($client)->getHandle('activity-id', 'run-id')->describe();
            self::fail('Expected ActivityNotFoundException');
        } catch (ActivityNotFoundException $e) {
            self::assertSame('activity-id', $e->activityId);
            self::assertSame('run-id', $e->runId);
        }
    }

    public function testNamespaceOverride(): void
    {
        $captured = new \ArrayObject();
        $client = $this->mockClient();
        $client->method('RequestCancelActivityExecution')->willReturnCallback(
            static function (RequestCancelActivityExecutionRequest $request) use ($captured) {
                $captured['cancel'] = $request;
                return new RequestCancelActivityExecutionResponse();
            },
        );
        $client->method('CountActivityExecutions')->willReturnCallback(
            static function (CountActivityExecutionsRequest $request) use ($captured) {
                $captured['count'] = $request;
                return new CountActivityExecutionsResponse();
            },
        );
        $activityClient = ActivityClient::create($client);

        $activityClient->getHandle('activity-id', 'run-id', 'other-namespace')->cancel();
        $activityClient->countExecutions('query', 'other-namespace');

        self::assertSame('other-namespace', $captured['cancel']->getNamespace());
        self::assertSame('other-namespace', $captured['count']->getNamespace());
    }

    private static function validOptions(): StartActivityOptions
    {
        return StartActivityOptions::new()
            ->withId('activity-id')
            ->withTaskQueue('queue')
            ->withStartToCloseTimeout(10);
    }

    private static function resultResponse(string $runId, array $values): PollActivityExecutionResponse
    {
        return (new PollActivityExecutionResponse())->setRunId($runId)->setOutcome(
            (new ActivityExecutionOutcome())->setResult(
                EncodedValues::fromValues($values, DataConverter::createDefault())->toPayloads(),
            ),
        );
    }

    private function mockClient(): ServiceClientInterface&MockObject
    {
        $client = $this->createMock(ServiceClientInterface::class);
        $client->method('withContext')->willReturnSelf();
        $client->method('getContext')->willReturn(Context::default());

        return $client;
    }

    private static function describeResponse(): DescribeActivityExecutionResponse
    {
        return (new DescribeActivityExecutionResponse())->setInfo(
            (new ActivityExecutionInfo())
                ->setActivityType(new ActivityType(['name' => 'type']))
                ->setTaskQueue('queue'),
        );
    }
}

final class RecordingInterceptor implements ActivityClientCallsInterceptor
{
    use ActivityClientCallsInterceptorTrait {
        start as private defaultStart;
        getResult as private defaultGetResult;
        describe as private defaultDescribe;
        cancel as private defaultCancel;
        terminate as private defaultTerminate;
    }

    /** @var list<string> */
    public array $calls = [];

    public function start(StartInput $input, callable $next): ActivityHandle
    {
        $this->calls[] = 'start';
        return $next($input->with(header: $input->header->withValue('trace', 'traced')));
    }

    public function getResult(GetResultInput $input, callable $next): ?ValuesInterface
    {
        $this->calls[] = 'getResult';
        return $next($input);
    }

    public function describe(DescribeInput $input, callable $next): ActivityExecutionDescription
    {
        $this->calls[] = 'describe';
        return $next($input);
    }

    public function cancel(CancelInput $input, callable $next): void
    {
        $this->calls[] = 'cancel';
        $next($input);
    }

    public function terminate(TerminateInput $input, callable $next): void
    {
        $this->calls[] = 'terminate';
        $next($input);
    }
}
