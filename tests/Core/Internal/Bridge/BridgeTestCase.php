<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Internal\Bridge;

use Coresdk\ActivityHeartbeat;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Revolt\EventLoop;
use Spiral\Attributes\AttributeReader;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Bridge\BridgeConnection;
use Temporal\Internal\Bridge\CoreEnvironment;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Testing\Replay\HistoryJsonCodec;
use Temporal\Tests\Unit\Client\Stub\LoggerSpy;
use Temporal\Worker\Core\CoreOptions;
use Temporal\Worker\Core\CoreRole;
use Temporal\Worker\Core\CoreWorkerConfig;
use Temporal\Worker\Core\CoreWorkerFactory;

final class BridgeTestCase extends TestCase
{
    private const CLOSED_ADDRESS = '127.0.0.1:1';
    private const GET_SYSTEM_INFO = '/temporal.api.workflowservice.v1.WorkflowService/GetSystemInfo';
    private const CALL_TIMEOUT_MS = 5000;
    private const STARTED_EVENT_ID = 1;
    private const MORE_EVENTS_THAN_ONE_FETCH = 300;

    public function testSharedBridgeIsTheCurrentOne(): void
    {
        $bridge = Bridge::shared();

        self::assertSame($bridge, Bridge::shared());
        self::assertTrue(Bridge::started());
        self::assertTrue(Bridge::isCurrent($bridge));
        self::assertFalse(Bridge::isCurrent((new \ReflectionClass(Bridge::class))->newInstanceWithoutConstructor()));
    }

    public function testMissingLibraryIsReported(): void
    {
        $this->expectExceptionMessage('Unable to read the sdk-core bridge library "/nonexistent/libtemporal_php_bridge.so"');
        self::withServer([CoreEnvironment::BRIDGE_LIB => '/nonexistent/libtemporal_php_bridge.so'], static fn(): Bridge => new Bridge());
    }

    public function testRuntimeErrorIsReported(): void
    {
        $this->expectExceptionMessage('tpb_runtime_new failed: Invalid Prometheus address not an address');
        self::withServer([CoreEnvironment::PROMETHEUS => 'not an address'], static fn(): Bridge => new Bridge());
    }

    public function testRuntimeNeedsAThread(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::withServer([CoreEnvironment::THREADS => '0'], static fn(): Bridge => new Bridge());
    }

    public function testCoreLogsReachTheLogger(): void
    {
        $bridge = self::withServer([CoreEnvironment::LOG => 'info'], static fn(): Bridge => new Bridge());
        $logger = new LoggerSpy();
        $bridge->useLogger($logger);

        $core = self::replayer($bridge, self::STARTED_EVENT_ID);
        $bridge->nextEvents(0);
        $bridge->shutdownWorkers([1 => $core]);

        self::assertSame(LogLevel::INFO, $logger->records[0]['level']);
        self::assertSame('temporalio_sdk_core', $logger->records[0]['context']['target']);
    }

    public function testCallToAClosedPortIsUnavailable(): void
    {
        $bridge = Bridge::shared();
        $client = $bridge->newClient(BridgeConnection::client(self::CLOSED_ADDRESS, null));

        $result = $bridge->awaitCall($bridge->startCall($client, self::GET_SYSTEM_INFO, '', ['x-test' => ['1']], self::CALL_TIMEOUT_MS));
        $bridge->freeClient($client);

        self::assertSame(StatusCode::UNAVAILABLE, $result[0]);
    }

    public function testCallToASilentServerTimesOut(): void
    {
        $server = \stream_socket_server('tcp://127.0.0.1:0');
        $bridge = Bridge::shared();
        $client = $bridge->newClient(BridgeConnection::client((string) \stream_socket_get_name($server, false), null));

        $tag = $bridge->startCall($client, self::GET_SYSTEM_INFO, '', [], 50);
        $pending = $bridge->pollCall($tag, 0);
        $result = $bridge->awaitCall($tag);
        $bridge->freeClient($client);
        \fclose($server);

        self::assertNull($pending);
        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $result[0]);
    }

    public function testConnectToAClosedPortIsUnavailable(): void
    {
        $bridge = Bridge::shared();
        $client = $bridge->newClient(BridgeConnection::client(self::CLOSED_ADDRESS, null));

        $result = $bridge->pollCall($bridge->startConnect($client, self::CALL_TIMEOUT_MS), self::CALL_TIMEOUT_MS);
        $bridge->freeClient($client);

        self::assertSame(StatusCode::UNAVAILABLE, $result[0] ?? null);
    }

    public function testForgottenCallResultIsDropped(): void
    {
        $bridge = Bridge::shared();
        $client = $bridge->newClient(BridgeConnection::client(self::CLOSED_ADDRESS, null));

        $tag = $bridge->startCall($client, self::GET_SYSTEM_INFO, '', [], self::CALL_TIMEOUT_MS);
        $bridge->forgetCall($tag);
        $result = $bridge->pollCall($tag, 1000);
        $bridge->freeClient($client);

        self::assertNull($result);
    }

    public function testCallInsideAFiberWaitsForTheEventPipe(): void
    {
        $bridge = Bridge::shared();
        $client = $bridge->newClient(BridgeConnection::client(self::CLOSED_ADDRESS, null));
        $pipe = $bridge->openEventPipe();
        $readable = EventLoop::onReadable($pipe, static function () use ($bridge, $pipe): void {
            \fread($pipe, 1024);
            $bridge->nextEvents(0);
        });
        $result = null;
        EventLoop::queue(static function () use ($bridge, $client, $readable, &$result): void {
            $result = $bridge->awaitCall($bridge->startCall($client, self::GET_SYSTEM_INFO, '', [], self::CALL_TIMEOUT_MS));
            EventLoop::cancel($readable);
        });

        EventLoop::run();
        self::assertSame($pipe, $bridge->openEventPipe());
        $bridge->closeEventPipe();
        $bridge->closeEventPipe();
        $bridge->freeClient($client);

        self::assertSame(StatusCode::UNAVAILABLE, $result[0] ?? null);
    }

    public function testWorkerCallsReportFailuresAsEvents(): void
    {
        $bridge = Bridge::shared();
        $core = self::replayer($bridge, self::STARTED_EVENT_ID);

        $bridge->pollActivityTask($core, 7);
        $bridge->completeWorkflowActivation($core, 8, 'x');
        $bridge->completeActivityTask($core, 9, 'x');
        $events = [];
        while (\count($events) < 3) {
            \array_push($events, ...$bridge->nextEvents(Bridge::POLL_TIMEOUT_MS));
        }
        \usort($events, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        self::assertSame([7, Bridge::KIND_ACTIVITY_TASK, Bridge::STATUS_SHUTDOWN, ''], $events[0]);
        self::assertSame([8, Bridge::KIND_WORKFLOW_COMPLETED], \array_slice($events[1], 0, 2));
        self::assertStringStartsWith('Decode failure', $events[1][3]);
        self::assertSame([9, Bridge::KIND_ACTIVITY_COMPLETED], \array_slice($events[2], 0, 2));
        self::assertSame([], $bridge->shutdownWorkers([10 => $core]));
    }

    public function testHeartbeatIsValidated(): void
    {
        $bridge = Bridge::shared();
        $core = self::replayer($bridge, self::STARTED_EVENT_ID);

        $bridge->recordActivityHeartbeat($core, (new ActivityHeartbeat(['task_token' => 'unknown']))->serializeToString());
        try {
            $this->expectExceptionMessage('Unable to record the activity heartbeat');
            $bridge->recordActivityHeartbeat($core, 'garbage');
        } finally {
            $bridge->shutdownWorkers([1 => $core]);
        }
    }

    public function testShutdownEndsThePolls(): void
    {
        $bridge = Bridge::shared();
        $core = self::replayer($bridge, self::STARTED_EVENT_ID);

        $bridge->initiateShutdown($core);
        $bridge->initiateShutdown($core);
        $bridge->pollWorkflowActivation($core, 1);
        $events = [];
        while ($events === []) {
            $events = $bridge->nextEvents(Bridge::POLL_TIMEOUT_MS);
        }

        self::assertSame([[1, Bridge::KIND_WORKFLOW_ACTIVATION, Bridge::STATUS_SHUTDOWN, '']], $events);
        self::assertSame([], $bridge->shutdownWorkers([1 => $core]));
    }

    public function testPeekedEventsAreReturnedAgain(): void
    {
        $bridge = Bridge::shared();
        $core = self::replayer($bridge, self::STARTED_EVENT_ID);

        $bridge->completeWorkflowActivation($core, 1, 'x');
        $peeked = $bridge->peekEvents();
        $bridge->completeWorkflowActivation($core, 2, 'x');
        $events = $bridge->nextEvents(0);
        $bridge->shutdownWorkers([3 => $core]);

        self::assertSame([1], \array_column($peeked, 0));
        self::assertSame([1, 2], \array_column($events, 0));
    }

    public function testOneFetchDrainsTheWholeQueue(): void
    {
        $bridge = Bridge::shared();
        $core = self::replayer($bridge, self::STARTED_EVENT_ID);

        for ($i = 0; $i < self::MORE_EVENTS_THAN_ONE_FETCH; ++$i) {
            $bridge->completeWorkflowActivation($core, 1, 'x');
        }
        $events = $bridge->nextEvents(0);
        $bridge->shutdownWorkers([2 => $core]);

        self::assertCount(self::MORE_EVENTS_THAN_ONE_FETCH, $events);
    }

    /**
     * @param array<string, string> $server
     */
    private static function withServer(array $server, \Closure $create): Bridge
    {
        $_SERVER = $server + $_SERVER;
        try {
            return $create();
        } finally {
            foreach (\array_keys($server) as $name) {
                unset($_SERVER[$name]);
            }
        }
    }

    private static function replayer(Bridge $bridge, int $lastEventId): \FFI\CData
    {
        $factory = CoreWorkerFactory::create();
        $config = new CoreWorkerConfig(CoreOptions::create(null, null, null, null, null), new Marshaller(new AttributeMapperFactory(new AttributeReader())));
        $history = (new HistoryJsonCodec())->decode((string) \file_get_contents(__DIR__ . '/../../../Fixtures/history/squence-workflow-damaged.json'), $lastEventId);

        return $bridge->newReplayer($config->build($factory->newWorker('default'), CoreRole::Workflow), $history->serializeToString(), 'replay');
    }
}
