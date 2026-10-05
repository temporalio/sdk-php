<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Client;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use Temporal\Api\Workflowservice\V1\DescribeNamespaceRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\Core\CoreCloudServiceStub;
use Temporal\Client\GRPC\Core\CoreOperatorServiceStub;
use Temporal\Client\GRPC\Core\CoreWorkflowServiceStub;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Bridge\BridgeConnection;
use Temporal\Testing\CoreTestServiceStub;
use Temporal\Tests\Core\DevServer;

final class CoreStubTestCase extends TestCase
{
    private const CLOSED_ADDRESS = '127.0.0.1:1';
    private const FREE_PORT_PROBE = 'tcp://127.0.0.1:0';
    private const WAIT_FOR_FAILURE_MICROSECONDS = 5_000_000;
    private const SHORT_WAIT_MICROSECONDS = 100_000;
    private const CALL_TIMEOUT_MICROSECONDS = 200_500;
    private const CLIENT_PREFACE_BYTES = 24;
    private const TIMEOUT_BEYOND_GRPC_MICROSECONDS = 400_000_000_000_000_000;
    private const PIPE_READ_BYTES = 1024;

    /** @var resource|null */
    private $listener = null;

    protected function tearDown(): void
    {
        if ($this->listener !== null) {
            \fclose($this->listener);
        }
    }

    public static function provideStubClasses(): iterable
    {
        yield 'workflow service' => [CoreWorkflowServiceStub::class];
        yield 'operator service' => [CoreOperatorServiceStub::class];
        yield 'cloud service' => [CoreCloudServiceStub::class];
        yield 'test service' => [CoreTestServiceStub::class];
    }

    /**
     * @param class-string<CoreWorkflowServiceStub|CoreOperatorServiceStub|CoreCloudServiceStub|CoreTestServiceStub> $class
     */
    #[DataProvider('provideStubClasses')]
    public function testStubIsIdleUntilItConnects(string $class): void
    {
        $stub = new $class(self::CLOSED_ADDRESS);

        $this->assertSame(self::CLOSED_ADDRESS, $stub->getTarget());
        $this->assertSame(ConnectionState::Idle->value, $stub->getConnectivityState());
    }

    public function testClosedPortEndsInTransientFailure(): void
    {
        $stub = new CoreWorkflowServiceStub(self::CLOSED_ADDRESS);

        $this->assertFalse($stub->waitForReady(self::WAIT_FOR_FAILURE_MICROSECONDS));
        $this->assertSame(ConnectionState::TransientFailure->value, $stub->getConnectivityState());
    }

    public function testCallToClosedPortReturnsUnavailable(): void
    {
        $stub = new CoreWorkflowServiceStub(self::CLOSED_ADDRESS);

        [$response, $status] = $stub->GetSystemInfo(
            new GetSystemInfoRequest(),
            ['Trace-Bin' => ["\x00\x01"], 'Trace' => ['plain']],
            ['timeout' => self::WAIT_FOR_FAILURE_MICROSECONDS],
        )->wait();

        $this->assertNull($response);
        $this->assertSame(StatusCode::UNAVAILABLE, $status->code);
        $this->assertNotSame('', $status->details);
        $this->assertSame([], $status->metadata);
    }

    public function testTimeoutLongerThanGrpcCanEncodeIsCapped(): void
    {
        $stub = new CoreWorkflowServiceStub(self::CLOSED_ADDRESS);

        [, $status] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::TIMEOUT_BEYOND_GRPC_MICROSECONDS])->wait();

        $this->assertSame(StatusCode::UNAVAILABLE, $status->code);
    }

    public function testLargestIntegerTimeoutIsAccepted(): void
    {
        $stub = new CoreWorkflowServiceStub(self::CLOSED_ADDRESS);

        [, $status] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => \PHP_INT_MAX])->wait();

        $this->assertSame(StatusCode::UNAVAILABLE, $status->code);
        $this->assertFalse($stub->waitForReady(\PHP_INT_MAX));
    }

    public function testInvalidMetadataKeyIsRejectedBeforeTheCall(): void
    {
        $stub = new CoreWorkflowServiceStub(self::CLOSED_ADDRESS);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata keys must be nonempty strings containing only alphanumeric characters, hyphens, underscores and dots');

        $stub->GetSystemInfo(new GetSystemInfoRequest(), ['bad key' => ['x']]);
    }

    public function testCallResultIsTakenOnce(): void
    {
        $call = (new CoreWorkflowServiceStub(self::CLOSED_ADDRESS))->GetSystemInfo(new GetSystemInfoRequest());
        $call->wait();

        $this->expectException(\LogicException::class);

        $call->wait();
    }

    public function testHandshakeThatNeverEndsStaysConnectingUntilClosed(): void
    {
        $stub = new CoreWorkflowServiceStub($this->silentListener(), BridgeConnection::tls(null, null, null, null));

        $this->assertSame(ConnectionState::Connecting->value, $stub->getConnectivityState(true));
        $this->assertFalse($stub->waitForReady(self::SHORT_WAIT_MICROSECONDS));
        $this->assertSame(ConnectionState::Connecting->value, $stub->getConnectivityState());

        $stub->close();
        $stub->close();

        $this->expectExceptionObject(new \RuntimeException('getConnectivityState error.Channel is already closed.', 1));

        $stub->getConnectivityState();
    }

    public function testCallWithoutAnAnswerExceedsItsDeadline(): void
    {
        $stub = new CoreWorkflowServiceStub($this->silentListener());

        $this->assertTrue($stub->waitForReady(self::WAIT_FOR_FAILURE_MICROSECONDS));
        $this->assertSame(ConnectionState::Ready->value, $stub->getConnectivityState(true));
        [$response, $status] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::CALL_TIMEOUT_MICROSECONDS])->wait();

        $this->assertNull($response);
        $this->assertSame(StatusCode::DEADLINE_EXCEEDED, $status->code);
        $this->assertSame([], $status->metadata);
    }

    public static function provideBrokenServerReplies(): iterable
    {
        yield 'connection closed' => [null];
        yield 'not http/2' => ["HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"];
    }

    #[DataProvider('provideBrokenServerReplies')]
    public function testConnectionBrokenByTheServerIsUnavailable(?string $reply): void
    {
        $stub = new CoreWorkflowServiceStub($this->silentListener());
        $call = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::WAIT_FOR_FAILURE_MICROSECONDS]);
        $connection = \stream_socket_accept($this->listener, self::WAIT_FOR_FAILURE_MICROSECONDS / 1_000_000);
        $this->assertIsResource($connection);
        if ($reply !== null) {
            \fread($connection, self::CLIENT_PREFACE_BYTES);
            \fwrite($connection, $reply);
        }
        \fclose($connection);

        [$response, $status] = $call->wait();

        $this->assertNull($response);
        $this->assertSame(StatusCode::UNAVAILABLE, $status->code);
    }

    public function testStubFromAnotherProcessIsRejected(): void
    {
        $stub = new CoreWorkflowServiceStub(self::CLOSED_ADDRESS);
        \Closure::bind(static function (CoreWorkflowServiceStub $stub): void {
            $stub->bridge = (new \ReflectionClass(Bridge::class))->newInstanceWithoutConstructor();
        }, null, CoreWorkflowServiceStub::class)($stub);

        try {
            $stub->getConnectivityState();
            $this->fail('The stub used a bridge of another process');
        } catch (\LogicException $e) {
            $this->assertSame(Bridge::FORKED_AFTER_START, $e->getMessage());
        }

        $stub->close();
        $this->expectExceptionObject(new \RuntimeException('getConnectivityState error.Channel is already closed.', 1));

        $stub->getConnectivityState();
    }

    public function testCloseEndsTheCallInFlight(): void
    {
        $stub = new CoreWorkflowServiceStub($this->silentListener());
        $call = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::WAIT_FOR_FAILURE_MICROSECONDS]);

        $stub->close();

        $this->expectExceptionObject(new \RuntimeException('startBatch Error. Channel is closed', 1));

        $call->wait();
    }

    public function testCloseWakesTheCallThatWaitsInAFiber(): void
    {
        $stub = new CoreWorkflowServiceStub($this->silentListener());
        $bridge = Bridge::shared();
        $pipe = $bridge->openEventPipe();
        $readable = EventLoop::onReadable($pipe, static function () use ($bridge, $pipe): void {
            \fread($pipe, self::PIPE_READ_BYTES);
            $bridge->nextEvents(0);
        });
        $failure = null;
        $started = \microtime(true);
        EventLoop::queue(static function () use ($stub, $readable, &$failure): void {
            try {
                $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::WAIT_FOR_FAILURE_MICROSECONDS])->wait();
            } catch (\RuntimeException $e) {
                $failure = $e;
            }
            EventLoop::cancel($readable);
        });
        EventLoop::delay(self::SHORT_WAIT_MICROSECONDS / 1_000_000, $stub->close(...));

        EventLoop::run();
        $bridge->closeEventPipe();

        $this->assertEquals(new \RuntimeException('startBatch Error. Channel is closed', 1), $failure);
        $this->assertLessThan(self::WAIT_FOR_FAILURE_MICROSECONDS / 1_000_000, \microtime(true) - $started);
    }

    public function testClosedStubRejectsCallsAndQueries(): void
    {
        $stub = new CoreWorkflowServiceStub(self::CLOSED_ADDRESS);
        $stub->close();
        $closed = static function (\Closure $use): string {
            try {
                $use();
            } catch (\RuntimeException|\InvalidArgumentException $e) {
                return \sprintf('%s(%d): %s', $e::class, $e->getCode(), $e->getMessage());
            }

            return 'no exception';
        };

        $this->assertSame([
            'InvalidArgumentException(1): Call cannot be constructed from a closed Channel',
            'RuntimeException(1): getConnectivityState error.Channel is already closed.',
            'RuntimeException(1): getConnectivityState error.Channel is already closed.',
            'RuntimeException(1): getTarget error.Channel is already closed.',
        ], [
            $closed(static fn() => $stub->GetSystemInfo(new GetSystemInfoRequest())),
            $closed(static fn() => $stub->getConnectivityState(true)),
            $closed(static fn() => $stub->waitForReady(self::SHORT_WAIT_MICROSECONDS)),
            $closed(static fn() => $stub->getTarget()),
        ]);
    }

    public function testServerAnswersWithResponseAndWithStatusDetails(): void
    {
        $stub = new CoreWorkflowServiceStub(DevServer::address());

        $this->assertTrue($stub->waitForReady(self::WAIT_FOR_FAILURE_MICROSECONDS));
        [$info, $ok] = $stub->GetSystemInfo(new GetSystemInfoRequest())->wait();
        [$missing, $notFound] = $stub->DescribeNamespace((new DescribeNamespaceRequest())->setNamespace('missing-' . \bin2hex(\random_bytes(4))))->wait();

        $this->assertInstanceOf(GetSystemInfoResponse::class, $info);
        $this->assertSame(StatusCode::OK, $ok->code);
        $this->assertNull($missing);
        $this->assertSame(StatusCode::NOT_FOUND, $notFound->code);
        $this->assertNotSame('', $notFound->metadata['grpc-status-details-bin'][0]);
    }

    private function silentListener(): string
    {
        $listener = \stream_socket_server(self::FREE_PORT_PROBE);
        $this->assertIsResource($listener);
        $this->listener = $listener;

        return (string) \stream_socket_get_name($listener, false);
    }
}
