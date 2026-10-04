<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Worker;

use Coresdk\Activity_task\ActivityCancelReason;
use Coresdk\Activity_task\ActivityTask;
use Coresdk\Activity_task\Cancel;
use Coresdk\Activity_task\Start;
use Coresdk\ActivityTaskCompletion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spiral\Attributes\AttributeReader;
use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\DoNotCompleteOnResultException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\TransportException;
use Temporal\Internal\Activity\ActivityContext;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Testing\Replay\HistoryJsonCodec;
use Temporal\Worker\Core\ActivityTasks;
use Temporal\Worker\Core\CoreOptions;
use Temporal\Worker\Core\CoreRole;
use Temporal\Worker\Core\CoreWorkerConfig;
use Temporal\Worker\Core\CoreWorkerFactory;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\Server\ServerRequest;

final class ActivityTasksTestCase extends TestCase
{
    private const TOKEN = 'token';
    private const STARTED_EVENT_ID = 1;

    public function testActivityWithoutResultFailsOnlyItsTask(): void
    {
        $completion = $this->handle(static fn(): array => [], 'Act');

        self::assertSame(self::TOKEN, $completion->getTaskToken());
        self::assertSame('Activity produced no result', $completion->getResult()->getFailed()->getFailure()->getMessage());
    }

    public function testDispatchErrorFailsOnlyItsTask(): void
    {
        $completion = $this->handle(static fn(): never => throw new \RuntimeException('dispatch broke'), 'Act');

        self::assertSame(self::TOKEN, $completion->getTaskToken());
        self::assertSame('dispatch broke', $completion->getResult()->getFailed()->getFailure()->getMessage());
    }

    public function testSideEffectWithoutInputCompletesWithoutResult(): void
    {
        $completion = $this->handle(static fn(): array => [], ActivityTasks::SIDE_EFFECT);

        self::assertTrue($completion->getResult()->hasCompleted());
        self::assertNull($completion->getResult()->getCompleted()->getResult());
    }

    public function testSideEffectReturnsItsInput(): void
    {
        $completion = $this->handle(static fn(): array => [], ActivityTasks::SIDE_EFFECT, ['input' => [DataConverter::createDefault()->toPayload(7)]]);

        self::assertSame('7', $completion->getResult()->getCompleted()->getResult()->getData());
    }

    public function testResultAndHeartbeatDetailsAreDispatched(): void
    {
        $request = null;
        $completion = $this->handle(static function (array $commands) use (&$request): array {
            $request = $commands[0];
            return [new SuccessClientResponse($request->getID(), EncodedValues::fromValues(['done']))];
        }, 'Act', [
            'input' => [DataConverter::createDefault()->toPayload('in')],
            'heartbeat_details' => [DataConverter::createDefault()->toPayload('beat')],
            'is_local' => true,
        ]);

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame(['InvokeLocalActivity', 1], [$request->getName(), $request->getOptions()['heartbeatDetails']]);
        self::assertSame(['in', 'beat'], [$request->getPayloads()->getValue(0), $request->getPayloads()->getValue(1)]);
        self::assertSame('"done"', $completion->getResult()->getCompleted()->getResult()->getData());
    }

    public function testAsyncCompletionIsReported(): void
    {
        $completion = $this->handle(static fn(array $commands): array => [new FailedClientResponse($commands[0]->getID(), new DoNotCompleteOnResultException())], 'Act');

        self::assertTrue($completion->getResult()->hasWillCompleteAsync());
    }

    public function testUndecodableTaskWithoutTokenIsRethrown(): void
    {
        $this->expectException(\Exception::class);
        self::tasks(static fn(): array => [])->handle(\FFI::cdef()->new('int'), 'queue', "\xff");
    }

    public function testHeartbeatOfAnUnknownTaskIsIgnored(): void
    {
        self::assertSame([], self::tasks(static fn(): array => [])->call(ActivityContext::HEARTBEAT_METHOD, self::heartbeat()));
    }

    public function testOnlyHeartbeatsAreSupported(): void
    {
        $this->expectException(TransportException::class);
        self::tasks(static fn(): array => [])->call('Other', []);
    }

    public static function provideCancelReasons(): iterable
    {
        yield 'paused' => [ActivityCancelReason::PAUSED, ['paused' => true]];
        yield 'reset' => [ActivityCancelReason::RESET, ['reset' => true]];
        yield 'canceled' => [ActivityCancelReason::CANCELLED, ['canceled' => true]];
    }

    #[DataProvider('provideCancelReasons')]
    public function testHeartbeatReportsTheCancelReason(int $reason, array $expected): void
    {
        $bridge = Bridge::shared();
        $worker = self::replayer($bridge);
        $tasks = new ActivityTasks();
        $heartbeats = [];
        $tasks->bind($bridge, static function (array $commands) use ($tasks, $worker, $reason, &$heartbeats): array {
            $tasks->handle($worker, 'queue', (new ActivityTask(['task_token' => self::TOKEN, 'cancel' => new Cancel(['reason' => $reason])]))->serializeToString());
            $heartbeats[] = $tasks->call(ActivityContext::HEARTBEAT_METHOD, self::heartbeat());
            return [new FailedClientResponse($commands[0]->getID(), new CanceledFailure('canceled'))];
        }, DataConverter::createDefault());

        $completion = self::completion($tasks->handle($worker, 'queue', self::start('Act')));
        $bridge->shutdownWorkers([1 => $worker]);

        self::assertSame([$expected], $heartbeats);
        self::assertTrue($completion->getResult()->hasCancelled());
    }

    private function handle(\Closure $dispatch, string $type, array $start = []): ActivityTaskCompletion
    {
        return self::completion(self::tasks($dispatch)->handle(\FFI::cdef()->new('int'), 'queue', self::start($type, $start)));
    }

    private static function tasks(\Closure $dispatch): ActivityTasks
    {
        $tasks = new ActivityTasks();
        $tasks->bind((new \ReflectionClass(Bridge::class))->newInstanceWithoutConstructor(), $dispatch, DataConverter::createDefault());

        return $tasks;
    }

    private static function start(string $type, array $start = []): string
    {
        return (new ActivityTask(['task_token' => self::TOKEN, 'start' => new Start(['activity_id' => '1', 'activity_type' => $type] + $start)]))->serializeToString();
    }

    private static function completion(?string $bytes): ActivityTaskCompletion
    {
        $completion = new ActivityTaskCompletion();
        $completion->mergeFromString((string) $bytes);

        return $completion;
    }

    private static function heartbeat(): array
    {
        return ['taskToken' => \base64_encode(self::TOKEN), 'details' => \base64_encode((new Payloads())->serializeToString())];
    }

    private static function replayer(Bridge $bridge): \FFI\CData
    {
        $config = new CoreWorkerConfig(CoreOptions::create(null, null, null, null, null), new Marshaller(new AttributeMapperFactory(new AttributeReader())));
        $history = (new HistoryJsonCodec())->decode((string) \file_get_contents(__DIR__ . '/../../Fixtures/history/squence-workflow-damaged.json'), self::STARTED_EVENT_ID);

        return $bridge->newReplayer($config->build(CoreWorkerFactory::create()->newWorker('default'), CoreRole::Workflow), $history->serializeToString(), 'replay');
    }
}
