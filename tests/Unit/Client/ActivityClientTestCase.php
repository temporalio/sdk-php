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
            (new PollActivityExecutionResponse())->setOutcome(
                (new ActivityExecutionOutcome())->setFailure(
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
            self::assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            self::assertSame('TestError', $e->getPrevious()->getType());
        }
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
