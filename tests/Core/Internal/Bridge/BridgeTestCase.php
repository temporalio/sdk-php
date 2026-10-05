<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Internal\Bridge;

use Coresdk\ActivityHeartbeat;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Revolt\EventLoop;
use Spiral\Attributes\AttributeReader;
use Temporal\Api\Enums\V1\WorkerStatus;
use Temporal\Api\Workflowservice\V1\ListWorkersRequest;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Bridge\BridgeConnection;
use Temporal\Internal\Bridge\CoreEnvironment;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Tests\Core\DevServer;
use Temporal\Tests\Unit\Client\Stub\LoggerSpy;
use Temporal\Tests\Core\Replayers;
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
    private const HEARTBEAT_INTERVAL_MS = '1000';
    private const HEARTBEAT_WAIT_SECONDS = 10.0;
    private const HEARTBEATS = 2;
    private const HEARTBEAT_CHECK_INTERVAL_US = 200_000;
    private const OTEL_METRIC_PERIODICITY_MS = '100';
    private const OTLP_ACCEPT_TIMEOUT_SECONDS = 5.0;
    private const OTLP_EXPORTS = 50;
    private const HTTP_HEAD_END = "\r\n\r\n";

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

        $core = Replayers::create($bridge, self::STARTED_EVENT_ID);
        $bridge->nextEvents(0);
        $bridge->shutdownWorkers([1 => $core]);

        self::assertSame(LogLevel::INFO, $logger->records[0]['level']);
        self::assertSame('temporalio_sdk_core', $logger->records[0]['context']['target']);
    }

    public function testRunningWorkerHeartbeatsAtTheConfiguredInterval(): void
    {
        $address = DevServer::address();
        $bridge = self::withServer([CoreEnvironment::WORKER_HEARTBEAT_INTERVAL => self::HEARTBEAT_INTERVAL_MS], static fn(): Bridge => new Bridge());
        $queue = \uniqid('core-heartbeat-', true);
        $core = self::startWorker($bridge, $address, $queue);

        $statuses = self::heartbeats(ServiceClient::create($address), $queue);
        $bridge->shutdownWorkers([1 => $core]);

        self::assertSame(\array_fill(0, self::HEARTBEATS, WorkerStatus::WORKER_STATUS_RUNNING), \array_values($statuses));
    }

    public function testOpenTelemetryEnvironmentExportsTaggedWorkerMetrics(): void
    {
        $collector = \stream_socket_server('tcp://127.0.0.1:0');
        $bridge = self::withServer([
            CoreEnvironment::OTEL_URL => 'http://' . (string) \stream_socket_get_name($collector, false) . '/v1/metrics',
            CoreEnvironment::OTEL_PROTOCOL => 'http',
            CoreEnvironment::OTEL_HEADERS => 'x-api-key = secret, x-tenant=php',
            CoreEnvironment::OTEL_METRIC_PERIODICITY => self::OTEL_METRIC_PERIODICITY_MS,
            CoreEnvironment::OTEL_USE_SECONDS_FOR_DURATIONS => 'true',
            CoreEnvironment::METRIC_PREFIX => 'php_',
            CoreEnvironment::METRIC_GLOBAL_TAGS => 'deployment=php-otlp',
        ], static fn(): Bridge => new Bridge());
        $core = self::startWorker($bridge, DevServer::address(), \uniqid('core-otel-', true));

        [$head, $body] = self::otlpRequest($collector, 'php_request');
        $bridge->shutdownWorkers([1 => $core]);

        self::assertStringStartsWith("POST /v1/metrics HTTP/1.1\r\n", $head);
        self::assertStringContainsString("\r\nx-api-key: secret\r\n", \strtolower($head));
        self::assertStringContainsString("\r\nx-tenant: php\r\n", \strtolower($head));
        self::assertStringContainsString('php-otlp', $body);
    }

    public function testPrometheusAndOpenTelemetryAreAlternatives(): void
    {
        $this->expectExceptionMessage('tpb_runtime_new failed: Prometheus and OpenTelemetry metrics cannot be used together');
        self::withServer([
            CoreEnvironment::PROMETHEUS => '127.0.0.1:0',
            CoreEnvironment::OTEL_URL => 'http://127.0.0.1:4317',
        ], static fn(): Bridge => new Bridge());
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
        $core = Replayers::create($bridge, self::STARTED_EVENT_ID);

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
        $core = Replayers::create($bridge, self::STARTED_EVENT_ID);

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
        $core = Replayers::create($bridge, self::STARTED_EVENT_ID);

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
        $core = Replayers::create($bridge, self::STARTED_EVENT_ID);

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
        $core = Replayers::create($bridge, self::STARTED_EVENT_ID);

        for ($i = 0; $i < self::MORE_EVENTS_THAN_ONE_FETCH; ++$i) {
            $bridge->completeWorkflowActivation($core, 1, 'x');
        }
        $events = $bridge->nextEvents(0);
        $bridge->shutdownWorkers([2 => $core]);

        self::assertCount(self::MORE_EVENTS_THAN_ONE_FETCH, $events);
    }

    private static function startWorker(Bridge $bridge, string $address, string $queue): \FFI\CData
    {
        $config = new CoreWorkerConfig(CoreOptions::create($address, null, null, null, null), new Marshaller(new AttributeMapperFactory(new AttributeReader())));
        $worker = CoreWorkerFactory::create()->newWorker($queue);
        $core = $bridge->newWorker(['connection' => $config->connection($worker, '')] + $config->build($worker, CoreRole::Workflow));
        $bridge->pollWorkflowActivation($core, 1);

        return $core;
    }

    /**
     * @param resource $collector
     * @return array{string, string}
     */
    private static function otlpRequest($collector, string $metric): array
    {
        for ($i = 0; $i < self::OTLP_EXPORTS; ++$i) {
            $connection = \stream_socket_accept($collector, self::OTLP_ACCEPT_TIMEOUT_SECONDS);
            self::assertNotFalse($connection, 'No OTLP export arrived');
            $request = '';
            while (!\str_contains($request, self::HTTP_HEAD_END)) {
                $request .= (string) \fread($connection, 8192);
            }
            [$head, $body] = \explode(self::HTTP_HEAD_END, $request, 2);
            \preg_match('/^content-length: (\d+)$/mi', $head, $length);
            while (\strlen($body) < (int) ($length[1] ?? 0)) {
                $body .= (string) \fread($connection, 8192);
            }
            \fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
            \fclose($connection);
            if (\str_contains($body, $metric)) {
                return [$head . self::HTTP_HEAD_END, $body];
            }
        }
        self::fail(\sprintf('No %s metric in %d OTLP exports', $metric, self::OTLP_EXPORTS));
    }

    /**
     * @return array<string, int>
     */
    private static function heartbeats(ServiceClient $client, string $queue): array
    {
        $statuses = [];
        $deadline = \microtime(true) + self::HEARTBEAT_WAIT_SECONDS;
        while (\count($statuses) < self::HEARTBEATS && \microtime(true) < $deadline) {
            foreach ($client->ListWorkers(new ListWorkersRequest(['namespace' => 'default']))->getWorkersInfo() as $info) {
                $heartbeat = $info->getWorkerHeartbeat();
                if ($heartbeat?->getTaskQueue() === $queue) {
                    $statuses[(string) $heartbeat->getHeartbeatTime()?->serializeToString()] = $heartbeat->getStatus();
                }
            }
            \usleep(self::HEARTBEAT_CHECK_INTERVAL_US);
        }

        return $statuses;
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

}
