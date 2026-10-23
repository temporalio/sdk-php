<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Client;

use Grpc\BaseStub;
use Grpc\ChannelCredentials;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Cloud\Cloudservice\V1\CloudServiceClient;
use Temporal\Api\Operatorservice\V1\OperatorServiceClient;
use Temporal\Api\Testservice\V1\TestServiceClient;
use Temporal\Api\Workflowservice\V1\DescribeNamespaceRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Tests\Core\DevServer;

final class GrpcStubTestCase extends TestCase
{
    private const CLOSED_ADDRESS = '127.0.0.1:1';
    private const FREE_PORT_PROBE = 'tcp://127.0.0.1:0';
    private const WAIT_FOR_FAILURE_MICROSECONDS = 5_000_000;
    private const BRIEF_WAIT_MICROSECONDS = 1_000_000;
    private const SHORT_WAIT_MICROSECONDS = 100_000;
    private const CALL_TIMEOUT_MICROSECONDS = 200_500;
    private const CLIENT_PREFACE_BYTES = 24;
    private const TIMEOUT_BEYOND_GRPC_MICROSECONDS = 400_000_000_000_000_000;
    private const CLOSED_CHANNEL_ERROR_CODE = 1;
    private const HTTP2_SETTINGS_FRAME = "\x00\x00\x00\x04\x00\x00\x00\x00\x00";

    /** @var resource|null */
    private $listener = null;

    /** @var resource|null */
    private $connection = null;

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            \fclose($this->connection);
        }
        if ($this->listener !== null) {
            \fclose($this->listener);
        }
    }

    public static function provideStubClasses(): iterable
    {
        yield 'workflow service' => [WorkflowServiceClient::class];
        yield 'operator service' => [OperatorServiceClient::class];
        yield 'cloud service' => [CloudServiceClient::class];
        yield 'test service' => [TestServiceClient::class];
    }

    /**
     * @param class-string<BaseStub> $class
     */
    #[DataProvider('provideStubClasses')]
    public function testStubIsIdleUntilItConnects(string $class): void
    {
        $stub = new $class(self::CLOSED_ADDRESS, ['credentials' => ChannelCredentials::createInsecure(), 'force_new' => true]);

        $this->assertSame('dns:///' . self::CLOSED_ADDRESS, $stub->getTarget());
        $this->assertSame(ConnectionState::Idle->value, $stub->getConnectivityState());
    }

    public static function provideTargets(): iterable
    {
        yield 'host and port' => ['localhost:7233', 'dns:///localhost:7233'];
        yield 'dns' => ['dns:///localhost:7233', 'dns:///localhost:7233'];
        yield 'ipv4' => ['ipv4:127.0.0.1:7233', 'ipv4:127.0.0.1:7233'];
        yield 'ipv6' => ['ipv6:[::1]:7233', 'ipv6:[::1]:7233'];
        yield 'unix' => ['unix:/tmp/temporal.sock', 'unix:/tmp/temporal.sock'];
    }

    #[DataProvider('provideTargets')]
    public function testTargetIsReportedAsExtGrpcReportsIt(string $address, string $target): void
    {
        $this->assertSame($target, self::stub($address)->getTarget());
    }

    public function testClosedPortEndsInTransientFailure(): void
    {
        $stub = self::stub(self::CLOSED_ADDRESS);

        $this->assertFalse($stub->waitForReady(self::BRIEF_WAIT_MICROSECONDS));
        $this->assertSame(ConnectionState::TransientFailure->value, $stub->getConnectivityState());
    }

    public function testCallToClosedPortReturnsUnavailable(): void
    {
        $stub = self::stub(self::CLOSED_ADDRESS);

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
        $stub = self::stub(self::CLOSED_ADDRESS);

        [, $status] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::TIMEOUT_BEYOND_GRPC_MICROSECONDS])->wait();

        $this->assertSame(StatusCode::UNAVAILABLE, $status->code);
    }

    public static function provideLargestTimeouts(): iterable
    {
        yield 'largest integer' => [\PHP_INT_MAX];
        yield 'deadline beyond the largest integer' => [\PHP_INT_MAX - 1];
    }

    #[DataProvider('provideLargestTimeouts')]
    public function testLargestIntegerTimeoutIsAccepted(int $timeout): void
    {
        $stub = self::stub(self::CLOSED_ADDRESS);

        [, $status] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => $timeout])->wait();

        $this->assertSame(StatusCode::UNAVAILABLE, $status->code);
    }

    public function testInvalidMetadataKeyIsRejectedBeforeTheCall(): void
    {
        $stub = self::stub(self::CLOSED_ADDRESS);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata keys must be nonempty strings containing only alphanumeric characters, hyphens, underscores and dots');

        $stub->GetSystemInfo(new GetSystemInfoRequest(), ['bad key' => ['x']]);
    }

    public function testCallResultIsTakenOnce(): void
    {
        $call = self::stub(self::CLOSED_ADDRESS)->GetSystemInfo(new GetSystemInfoRequest());
        $call->wait();

        $this->expectExceptionObject(new \LogicException('start_batch was called incorrectly', 8));

        $call->wait();
    }

    public function testHandshakeThatNeverEndsStaysConnectingUntilClosed(): void
    {
        $stub = self::stub($this->silentListener(), ChannelCredentials::createSsl());

        $this->assertSame(ConnectionState::Idle->value, $stub->getConnectivityState(true));
        $this->assertFalse($stub->waitForReady(self::SHORT_WAIT_MICROSECONDS));
        $this->assertSame(ConnectionState::Connecting->value, $stub->getConnectivityState());

        $stub->close();
        $stub->close();

        $this->expectExceptionObject(new \RuntimeException('getConnectivityState error.Channel is already closed.', self::CLOSED_CHANNEL_ERROR_CODE));

        $stub->getConnectivityState();
    }

    public function testCallWithoutAnAnswerExceedsItsDeadline(): void
    {
        $stub = $this->readyStub();

        $this->assertSame(ConnectionState::Ready->value, $stub->getConnectivityState(true));
        $call = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::CALL_TIMEOUT_MICROSECONDS]);
        [$response, $status] = $call->wait();

        $this->assertSame($stub->getTarget(), $call->getPeer());

        $this->assertNull($response);
        $this->assertSame(StatusCode::DEADLINE_EXCEEDED, $status->code);
        $this->assertSame('Deadline Exceeded', $status->details);
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
        $stub = self::stub($this->silentListener());
        $stub->getConnectivityState(true);
        $connection = $this->acceptAndStopListening();
        if ($reply !== null) {
            \fread($connection, self::CLIENT_PREFACE_BYTES);
            \fwrite($connection, $reply);
        }
        \fclose($connection);

        [$response, $status] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::WAIT_FOR_FAILURE_MICROSECONDS])->wait();

        $this->assertNull($response);
        $this->assertSame(StatusCode::UNAVAILABLE, $status->code);
    }

    public function testCloseEndsTheCallInFlight(): void
    {
        $stub = $this->readyStub();
        $call = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::BRIEF_WAIT_MICROSECONDS]);

        $stub->close();

        $this->expectExceptionObject(new \RuntimeException('startBatch Error. Channel is closed', self::CLOSED_CHANNEL_ERROR_CODE));

        $call->wait();
    }

    public function testCancelledCallEndsCancelled(): void
    {
        $stub = $this->readyStub();
        $call = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::BRIEF_WAIT_MICROSECONDS]);

        $call->cancel();
        [$response, $status] = $call->wait();

        $this->assertNull($response);
        $this->assertSame(StatusCode::CANCELLED, $status->code);
        $this->assertSame('CANCELLED', $status->details);
    }

    public function testClosedStubRejectsCallsAndQueries(): void
    {
        $stub = self::stub(self::CLOSED_ADDRESS);
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
        $stub = self::stub(DevServer::address());

        $this->assertTrue($stub->waitForReady(self::WAIT_FOR_FAILURE_MICROSECONDS));
        [$info, $ok] = $stub->GetSystemInfo(new GetSystemInfoRequest(), ['Trace-Bin' => ["\x00\x01"], 'Trace' => ['plain']])->wait();
        [$missing, $notFound] = $stub->DescribeNamespace((new DescribeNamespaceRequest())->setNamespace('missing-' . \bin2hex(\random_bytes(4))))->wait();

        $this->assertInstanceOf(GetSystemInfoResponse::class, $info);
        $this->assertSame(StatusCode::OK, $ok->code);
        $this->assertNull($missing);
        $this->assertSame(StatusCode::NOT_FOUND, $notFound->code);
        $this->assertNotSame('', $notFound->metadata['grpc-status-details-bin'][0]);
    }

    public function testCallCredentialsNeedASecureChannel(): void
    {
        $invoked = false;
        $call = self::stub(DevServer::address())->GetSystemInfo(
            new GetSystemInfoRequest(),
            [],
            ['call_credentials_callback' => static function () use (&$invoked): array {
                $invoked = true;

                return [];
            }],
        );

        [$response, $status] = $call->wait();

        $this->assertNull($response);
        $this->assertSame(StatusCode::UNAUTHENTICATED, $status->code);
        $this->assertFalse($invoked);
    }

    public function testCallCredentialsAddMetadataOnASecureChannel(): void
    {
        $certificate = self::selfSignedCertificate();
        $listener = \stream_socket_server(self::FREE_PORT_PROBE, context: \stream_context_create(['ssl' => [
            'local_cert' => $certificate,
            'alpn_protocols' => 'h2',
        ]]));
        $this->assertIsResource($listener);
        $this->listener = $listener;
        $stub = new WorkflowServiceClient((string) \stream_socket_get_name($listener, false), [
            'credentials' => ChannelCredentials::createSsl((string) \file_get_contents($certificate)),
            'grpc.ssl_target_name_override' => 'localhost',
            'force_new' => true,
        ]);
        $stub->getConnectivityState(true);
        $connection = \stream_socket_accept($listener, self::WAIT_FOR_FAILURE_MICROSECONDS / 1_000_000);
        $this->assertIsResource($connection);
        $this->connection = $connection;
        $this->assertTrue(\stream_socket_enable_crypto($connection, true, \STREAM_CRYPTO_METHOD_TLS_SERVER));
        \unlink($certificate);
        \fwrite($connection, self::HTTP2_SETTINGS_FRAME);
        $this->assertTrue($stub->waitForReady(self::WAIT_FOR_FAILURE_MICROSECONDS));
        $contexts = [];

        [, $status] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], [
            'timeout' => self::CALL_TIMEOUT_MICROSECONDS,
            'call_credentials_callback' => static function (\stdClass $context) use (&$contexts): array {
                $contexts[] = (array) $context;

                return ['authorization' => ['Bearer token']];
            },
        ])->wait();

        $this->assertSame(StatusCode::DEADLINE_EXCEEDED, $status->code);
        $this->assertSame([[
            'service_url' => 'https://localhost/temporal.api.workflowservice.v1.WorkflowService',
            'method_name' => 'GetSystemInfo',
        ]], $contexts);
    }

    public static function provideNamedTargets(): iterable
    {
        yield 'dns with an empty authority' => ['dns:///'];
        yield 'dns without an authority' => ['dns:'];
        yield 'dns with an authority' => ['dns://127.0.0.53/'];
        yield 'ipv4' => ['ipv4:'];
    }

    #[DataProvider('provideNamedTargets')]
    public function testNamedTargetReachesTheServer(string $prefix): void
    {
        $stub = self::stub($prefix . DevServer::address());

        [$info, $status] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::WAIT_FOR_FAILURE_MICROSECONDS])->wait();

        $this->assertSame(StatusCode::OK, $status->code, $status->details);
        $this->assertInstanceOf(GetSystemInfoResponse::class, $info);
    }

    public static function provideUnixTargets(): iterable
    {
        yield 'unix:/path' => ['unix:'];
        yield 'unix:///path' => ['unix://'];
    }

    #[DataProvider('provideUnixTargets')]
    public function testUnixTargetConnectsToTheSocket(string $prefix): void
    {
        $socket = \sys_get_temp_dir() . '/tpb-' . \getmypid() . '.sock';
        $listener = \stream_socket_server('unix://' . $socket);
        $this->assertIsResource($listener);
        $this->listener = $listener;
        $stub = self::stub($prefix . $socket);

        $stub->getConnectivityState(true);
        $connection = \stream_socket_accept($listener, self::WAIT_FOR_FAILURE_MICROSECONDS / 1_000_000);
        \unlink($socket);
        $this->assertIsResource($connection);
        \fwrite($connection, self::HTTP2_SETTINGS_FRAME);

        $this->assertTrue($stub->waitForReady(self::WAIT_FOR_FAILURE_MICROSECONDS));
    }

    public function testAnsweredCallsMakeTheStubReady(): void
    {
        $stub = self::stub(DevServer::address());

        $stub->GetSystemInfo(new GetSystemInfoRequest())->wait();
        $afterResponse = $stub->getConnectivityState();
        $stub->DescribeNamespace((new DescribeNamespaceRequest())->setNamespace('missing-' . \bin2hex(\random_bytes(4))))->wait();

        $this->assertSame(ConnectionState::Ready->value, $afterResponse);
        $this->assertSame(ConnectionState::Ready->value, $stub->getConnectivityState());
    }

    public function testCallToClosedPortMakesTheStubTransientFailure(): void
    {
        $stub = self::stub(self::CLOSED_ADDRESS);

        $stub->GetSystemInfo(new GetSystemInfoRequest())->wait();

        $this->assertSame(ConnectionState::TransientFailure->value, $stub->getConnectivityState());
    }

    public function testServerThatGoesAwayIdlesTheStubAndFailsItsCalls(): void
    {
        $stub = $this->readyStub();
        $call = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::WAIT_FOR_FAILURE_MICROSECONDS]);
        \fclose($this->listener);
        $this->listener = null;
        \fclose($this->connection);
        $this->connection = null;

        [, $lost] = $call->wait();
        $idle = $this->stateOnceNotReady($stub);
        [, $refused] = $stub->GetSystemInfo(new GetSystemInfoRequest(), [], ['timeout' => self::WAIT_FOR_FAILURE_MICROSECONDS])->wait();

        $this->assertSame(StatusCode::UNAVAILABLE, $lost->code);
        $this->assertSame(ConnectionState::Idle->value, $idle);
        $this->assertSame(StatusCode::UNAVAILABLE, $refused->code);
        $this->assertSame(ConnectionState::TransientFailure->value, $stub->getConnectivityState());
    }

    private static function stub(string $target, ?ChannelCredentials $credentials = null): WorkflowServiceClient
    {
        return new WorkflowServiceClient($target, ['credentials' => $credentials, 'force_new' => true]);
    }

    private function readyStub(): WorkflowServiceClient
    {
        $stub = self::stub($this->silentListener());
        $stub->getConnectivityState(true);
        $connection = \stream_socket_accept($this->listener, self::WAIT_FOR_FAILURE_MICROSECONDS / 1_000_000);
        $this->assertIsResource($connection);
        $this->connection = $connection;
        \fwrite($connection, self::HTTP2_SETTINGS_FRAME);
        $this->assertTrue($stub->waitForReady(self::WAIT_FOR_FAILURE_MICROSECONDS));

        return $stub;
    }

    /**
     * @return resource
     */
    private function acceptAndStopListening()
    {
        $connection = \stream_socket_accept($this->listener, self::WAIT_FOR_FAILURE_MICROSECONDS / 1_000_000);
        $this->assertIsResource($connection);
        \fclose($this->listener);
        $this->listener = null;

        return $connection;
    }

    private function stateOnceNotReady(WorkflowServiceClient $stub): int
    {
        $deadline = \microtime(true) + self::BRIEF_WAIT_MICROSECONDS / 1_000_000;
        do {
            $state = $stub->getConnectivityState();
        } while ($state === ConnectionState::Ready->value && \microtime(true) < $deadline);

        return $state;
    }

    private static function selfSignedCertificate(): string
    {
        $config = (string) \tempnam(\sys_get_temp_dir(), 'cnf');
        \file_put_contents($config, "[req]\ndistinguished_name = dn\n[dn]\n[san]\nsubjectAltName = DNS:localhost\n");
        $options = ['config' => $config, 'x509_extensions' => 'san', 'digest_alg' => 'sha256'];
        $key = \openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $certificate = \openssl_csr_sign(\openssl_csr_new(['commonName' => 'localhost'], $key, $options), null, $key, 1, $options);
        \openssl_x509_export($certificate, $pem);
        \openssl_pkey_export($key, $keyPem, null, $options);
        \unlink($config);
        $file = (string) \tempnam(\sys_get_temp_dir(), 'pem');
        \file_put_contents($file, $pem . $keyPem);

        return $file;
    }

    private function silentListener(): string
    {
        $listener = \stream_socket_server(self::FREE_PORT_PROBE);
        $this->assertIsResource($listener);
        $this->listener = $listener;

        return (string) \stream_socket_get_name($listener, false);
    }
}
