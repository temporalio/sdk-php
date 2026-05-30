<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType as NexusErrorType;
use Temporal\Nexus\Exception\HandlerException as NexusHandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Link;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use React\Promise\Deferred;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Internal\Transport\Router\CancelNexusOperation;
use Temporal\Internal\Transport\Router\InvokeNexusOperation;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Transport\Command\Client\NexusOperationStarted;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

// ── Test service definitions ─────────────────────────────────────────

#[Service(name: 'EchoService')]
interface EchoServiceInterface
{
    #[Operation]
    public function echo(string $input): string;

    #[AsyncOperation(output: 'string')]
    public function asyncEcho(string $input): OperationInfo;

    #[Operation]
    public function failOp(string $input): string;

    #[AsyncOperation(output: 'string')]
    public function cancelOp(string $input): OperationInfo;

    #[AsyncOperation(output: 'string')]
    public function cancelThrowsOp(string $input): OperationInfo;

    #[Operation]
    public function echoWithLinks(string $input): string;

    /**
     * Reads caller-side links from $details->links and serialises them into the result.
     */
    #[Operation]
    public function reportCallerLinks(string $input): string;

    /**
     * Reports whether $context->deadline was populated and roughly how far in the future it is.
     */
    #[Operation]
    public function reportDeadline(string $input): string;
}

class EchoServiceImpl implements EchoServiceInterface
{
    /** @var string[] */
    public array $canceledTokens = [];

    public function echo(string $input): string
    {
        return "echo:{$input}";
    }

    public function asyncEcho(string $input): OperationInfo
    {
        return new OperationInfo('async-token-' . $input, OperationState::Running);
    }

    public function failOp(string $input): string
    {
        throw OperationException::failed("fail:{$input}");
    }

    public function cancelOp(string $input): OperationInfo
    {
        return new OperationInfo('cancel-token-' . $input, OperationState::Running);
    }

    #[OperationCancel(operation: 'cancelOp')]
    public function cancelCancelOp(string $token): void
    {
        $this->canceledTokens[] = $token;
    }

    public function cancelThrowsOp(string $input): OperationInfo
    {
        return new OperationInfo('cancel-throws-token-' . $input, OperationState::Running);
    }

    #[OperationCancel(operation: 'cancelThrowsOp')]
    public function cancelCancelThrowsOp(string $token): void
    {
        throw new \RuntimeException("cancel routine blew up for {$token}");
    }

    public function echoWithLinks(string $input): string
    {
        Nexus::getCurrentOperationContext()->links->add(
            new Link('http://test.local/resource/1', 'test.Resource'),
            new Link('http://test.local/resource/2', 'test.Resource'),
        );
        return "linked:{$input}";
    }

    public function reportCallerLinks(string $input): string
    {
        $details = Nexus::getStartDetails();
        $parts = [];
        foreach ($details->links as $link) {
            $parts[] = "{$link->uri}|{$link->type}";
        }
        return \sprintf('caller-links:count=%d;items=[%s]', \count($details->links), \implode(';', $parts));
    }

    public function reportDeadline(string $input): string
    {
        $context = Nexus::getCurrentOperationContext();
        if ($context->deadline === null) {
            return 'deadline:none';
        }
        $delta = $context->deadline->getTimestamp() - (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp();
        return "deadline:set;delta_seconds={$delta}";
    }
}

// ── Integration tests ─────────────────────────────────────────────

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusTaskHandler::class)]
final class IntegrationTestCase extends AbstractUnit
{
    private InvokeNexusOperation $invokeRoute;
    private CancelNexusOperation $cancelRoute;
    private DataConverter $dataConverter;
    private EchoServiceImpl $serviceImpl;

    // ── Sync operation ───────────────────────────────────────────

    public function testSyncOperation(): void
    {
        $reply = $this->invoke('echo', 'hello');

        self::assertFalse($reply->isAsync());
        self::assertNull($reply->getToken());
        self::assertSame('echo:hello', $this->decodePayload($reply));
    }

    public function testSyncOperationWithDifferentInputs(): void
    {
        foreach (['simple', 'with spaces', 'unicode: привет'] as $input) {
            $reply = $this->invoke('echo', $input);
            self::assertFalse($reply->isAsync());
            self::assertSame("echo:{$input}", $this->decodePayload($reply), "Failed for input: {$input}");
        }
    }

    // ── Async operation ──────────────────────────────────────────

    public function testAsyncOperationReturnsTokenInReplyOptions(): void
    {
        $reply = $this->invoke('asyncEcho', 'test');

        self::assertTrue($reply->isAsync());
        self::assertSame('async-token-test', $reply->getToken());
        self::assertNull($reply->getPayloads(), 'async reply must not carry payloads');
    }

    // ── Operation error ──────────────────────────────────────────

    public function testOperationFailurePropagatesOperationException(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'failOp', 'reason');
        $deferred = new Deferred();

        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('fail:reason');
        $this->invokeRoute->handle($request, [], $deferred);
        $this->awaitReply($deferred);
    }

    // ── Handler error ────────────────────────────────────────────

    public function testHandlerErrorForUnknownService(): void
    {
        $request = $this->makeInvokeRequest('UnknownService', 'op', 'input');
        $deferred = new Deferred();
        try {
            $this->invokeRoute->handle($request, [], $deferred);
            $this->awaitReply($deferred);
            self::fail('Expected HandlerException');
        } catch (NexusHandlerException $e) {
            self::assertSame(NexusErrorType::NotFound, $e->errorType);
            self::assertSame('NOT_FOUND', $e->rawErrorType);
        }
    }

    public function testHandlerErrorForUnknownOperation(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'nonExistent', 'input');
        $deferred = new Deferred();
        try {
            $this->invokeRoute->handle($request, [], $deferred);
            $this->awaitReply($deferred);
            self::fail('Expected HandlerException');
        } catch (NexusHandlerException $e) {
            self::assertSame(NexusErrorType::NotFound, $e->errorType);
        }
    }

    // ── Cancel operation ─────────────────────────────────────────

    public function testCancelOperation(): void
    {
        $request = $this->makeCancelRequest('EchoService', 'cancelOp', 'my-token');
        $deferred = new Deferred();
        $this->cancelRoute->handle($request, [], $deferred);
        $this->awaitCancelResult($deferred);
        self::assertContains('my-token', $this->serviceImpl->canceledTokens);
    }

    public function testCancelUnknownService(): void
    {
        $request = $this->makeCancelRequest('UnknownService', 'op', 'token');
        $deferred = new Deferred();
        try {
            $this->cancelRoute->handle($request, [], $deferred);
            $this->awaitCancelResult($deferred);
            self::fail('Expected HandlerException');
        } catch (NexusHandlerException $e) {
            self::assertSame(NexusErrorType::NotFound, $e->errorType);
        }
    }

    public function testCancelHandlerThrowingIsConvertedToHandlerError(): void
    {
        $request = $this->makeCancelRequest('EchoService', 'cancelThrowsOp', 'boom-token');
        $deferred = new Deferred();

        $this->cancelRoute->handle($request, [], $deferred);

        $error = null;
        $deferred->promise()->then(
            null,
            static function (\Throwable $e) use (&$error): void {
                $error = $e;
            },
        );

        self::assertInstanceOf(
            NexusHandlerException::class,
            $error,
            'A throwing cancel routine must surface as a typed HandlerException rejection, never crash the worker.',
        );
        self::assertSame(NexusErrorType::Internal, $error->errorType);
        self::assertInstanceOf(\RuntimeException::class, $error->getPrevious());
        self::assertStringContainsString('boom-token', $error->getPrevious()->getMessage());
    }

    // ── Headers ──────────────────────────────────────────────────

    public function testRequestHeaders(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'echo',
            'requestId' => 'req-1',
            'headers' => ['Authorization' => 'Bearer token123'],
        ], EncodedValues::fromValues(['test'], $this->dataConverter));
        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $reply = $this->awaitReply($deferred);
        self::assertSame('echo:test', $this->decodePayload($reply));
    }

    // ── Caller-side Nexus-Link propagation ───────────────────────

    public function testCallerLinksPropagateFromOptionsToHandler(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'reportCallerLinks',
            'requestId' => 'links-1',
            'links' => [
                ['url' => 'https://caller.test/one', 'type' => 'example.one'],
                ['url' => 'https://caller.test/two', 'type' => 'example.two'],
            ],
        ], EncodedValues::fromValues(['ignored'], $this->dataConverter));

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $reply = $this->awaitReply($deferred);
        $result = $this->decodePayload($reply);

        self::assertStringContainsString('count=2', $result);
        self::assertStringContainsString('https://caller.test/one|example.one', $result);
        self::assertStringContainsString('https://caller.test/two|example.two', $result);
    }

    public function testMalformedCallerLinkRejectsRequest(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'reportCallerLinks',
            'requestId' => 'links-2',
            'links' => [
                ['url' => 'https://ok.test/a', 'type' => 'ok'],
                ['url' => 'https://missing-type.test/b'], // no type → reject
            ],
        ], EncodedValues::fromValues(['x'], $this->dataConverter));

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);

        $error = null;
        $deferred->promise()->then(null, static function (\Throwable $e) use (&$error): void {
            $error = $e;
        });
        self::assertInstanceOf(\Temporal\Nexus\Exception\HandlerException::class, $error);
        self::assertSame(
            \Temporal\Nexus\Exception\ErrorType::BadRequest,
            $error->errorType,
        );
        self::assertStringContainsString('missing or empty "type"', $error->getMessage());
    }

    public function testLinksPayloadWrongShapeRejectsRequest(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'reportCallerLinks',
            'requestId' => 'links-3',
            'links' => 'not-a-list',
        ], EncodedValues::fromValues(['x'], $this->dataConverter));

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);

        $error = null;
        $deferred->promise()->then(null, static function (\Throwable $e) use (&$error): void {
            $error = $e;
        });
        self::assertInstanceOf(\Temporal\Nexus\Exception\HandlerException::class, $error);
        self::assertSame(
            \Temporal\Nexus\Exception\ErrorType::BadRequest,
            $error->errorType,
        );
    }

    public function testAbsentLinksMeansEmptyList(): void
    {
        $reply = $this->invoke('reportCallerLinks', 'x');
        $result = $this->decodePayload($reply);

        self::assertStringContainsString('count=0', $result);
    }

    // ── Handler-side links → reply.options.links ────────────

    public function testHandlerAddedLinksAreSerializedIntoReplyOptions(): void
    {
        $reply = $this->invoke('echoWithLinks', 'hi');

        self::assertFalse($reply->isAsync());

        $links = $reply->getLinks();
        self::assertCount(2, $links);
        self::assertSame('http://test.local/resource/1', $links[0]['url']);
        self::assertSame('test.Resource', $links[0]['type']);
        self::assertSame('http://test.local/resource/2', $links[1]['url']);
    }

    public function testNoLinksMeansEmptyLinksList(): void
    {
        $reply = $this->invoke('echo', 'hello');

        self::assertSame([], $reply->getLinks(), 'links field must be empty when handler adds none');
    }

    // ── Deadline from Nexus timeout headers ──────────────────────

    public function testOperationTimeoutHeaderSetsDeadline(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'reportDeadline',
            'requestId' => 'dl-1',
            'headers' => ['Operation-Timeout' => '30s'],
        ], EncodedValues::fromValues(['x'], $this->dataConverter));

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $reply = $this->awaitReply($deferred);
        $result = $this->decodePayload($reply);

        self::assertStringContainsString('deadline:set', $result);
        self::assertMatchesRegularExpression('/delta_seconds=(2[5-9]|30|31)/', $result);
    }

    public function testRequestTimeoutUsedWhenOperationTimeoutAbsent(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'reportDeadline',
            'requestId' => 'dl-2',
            'headers' => ['Request-Timeout' => '10s'],
        ], EncodedValues::fromValues(['x'], $this->dataConverter));

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $reply = $this->awaitReply($deferred);
        $result = $this->decodePayload($reply);

        self::assertStringContainsString('deadline:set', $result);
        self::assertMatchesRegularExpression('/delta_seconds=([5-9]|1[0-1])/', $result);
    }

    public function testOperationTimeoutWinsOverRequestTimeout(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'reportDeadline',
            'requestId' => 'dl-3',
            'headers' => [
                'Request-Timeout'   => '5s',
                'Operation-Timeout' => '120s',
            ],
        ], EncodedValues::fromValues(['x'], $this->dataConverter));

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $reply = $this->awaitReply($deferred);
        $result = $this->decodePayload($reply);

        self::assertMatchesRegularExpression('/delta_seconds=1(1[5-9]|2[01])/', $result);
    }

    public function testMalformedTimeoutHeaderIsSilentlyIgnored(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'reportDeadline',
            'requestId' => 'dl-4',
            'headers' => ['Operation-Timeout' => 'garbage'],
        ], EncodedValues::fromValues(['x'], $this->dataConverter));

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $reply = $this->awaitReply($deferred);
        $result = $this->decodePayload($reply);

        self::assertStringContainsString('deadline:none', $result);
    }

    public function testCaseInsensitiveTimeoutHeaderLookup(): void
    {
        $request = $this->makeServerRequest('InvokeNexusOperation', [
            'service' => 'EchoService',
            'operation' => 'reportDeadline',
            'requestId' => 'dl-5',
            'headers' => ['operation-timeout' => '15s'],
        ], EncodedValues::fromValues(['x'], $this->dataConverter));

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $reply = $this->awaitReply($deferred);
        $result = $this->decodePayload($reply);

        self::assertStringContainsString('deadline:set', $result);
    }

    // ── Route names ──────────────────────────────────────────────

    public function testInvokeNexusOperationRouteName(): void
    {
        self::assertSame('InvokeNexusOperation', $this->invokeRoute->getName());
    }

    public function testCancelNexusOperationRouteName(): void
    {
        self::assertSame('CancelNexusOperation', $this->cancelRoute->getName());
    }

    protected function setUp(): void
    {
        $this->dataConverter = DataConverter::createDefault();

        $this->serviceImpl = new EchoServiceImpl();

        $reader = new \Temporal\Internal\Declaration\Reader\NexusServiceReader(new \Spiral\Attributes\AttributeReader());
        $prototype = $reader->fromClass($this->serviceImpl::class)->withInstance($this->serviceImpl);

        $repository = new \Temporal\Internal\Declaration\Prototype\NexusServiceCollection();
        $repository->add($prototype, false);

        $taskHandler = new \Temporal\Internal\Nexus\NexusTaskHandler($repository, $this->dataConverter);

        $marshaller = new \Temporal\Internal\Marshaller\Marshaller(
            new \Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory(new \Spiral\Attributes\AttributeReader()),
        );
        $this->invokeRoute = new InvokeNexusOperation($taskHandler, new \Temporal\Internal\Nexus\NexusInvocationRegistry(), $this->dataConverter, $marshaller);
        $this->cancelRoute = new CancelNexusOperation($taskHandler, $marshaller);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function invoke(string $operation, string $input): NexusOperationStarted
    {
        $request = $this->makeInvokeRequest('EchoService', $operation, $input);
        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        return $this->awaitReply($deferred);
    }

    private function decodePayload(NexusOperationStarted $reply): string
    {
        $payloads = $reply->getPayloads();
        self::assertNotNull($payloads, 'sync reply must carry a payload');
        return $payloads->getValue(0, 'string');
    }

    private function makeInvokeRequest(string $service, string $operation, string $input): ServerRequest
    {
        return $this->makeServerRequest('InvokeNexusOperation', [
            'service' => $service,
            'operation' => $operation,
            'requestId' => 'test-' . \bin2hex(\random_bytes(4)),
        ], EncodedValues::fromValues([$input], $this->dataConverter));
    }

    private function makeCancelRequest(string $service, string $operation, string $token): ServerRequest
    {
        return $this->makeServerRequest('CancelNexusOperation', [
            'service' => $service,
            'operation' => $operation,
            'operationToken' => $token,
        ]);
    }

    private function makeServerRequest(string $name, array $options, ?ValuesInterface $payloads = null): ServerRequest
    {
        return new ServerRequest(
            name: $name,
            info: new TickInfo(new \DateTimeImmutable()),
            options: $options,
            payloads: $payloads,
        );
    }

    private function awaitReply(Deferred $deferred): NexusOperationStarted
    {
        $result = null;
        $error = null;

        $deferred->promise()->then(
            static function ($value) use (&$result): void {
                $result = $value;
            },
            static function (\Throwable $e) use (&$error): void {
                $error = $e;
            },
        );

        if ($error !== null) {
            throw $error;
        }

        self::assertInstanceOf(NexusOperationStarted::class, $result);
        return $result;
    }

    private function awaitCancelResult(Deferred $deferred): ValuesInterface
    {
        $result = null;
        $error = null;

        $deferred->promise()->then(
            static function ($value) use (&$result): void {
                $result = $value;
            },
            static function (\Throwable $e) use (&$error): void {
                $error = $e;
            },
        );

        if ($error !== null) {
            throw $error;
        }

        self::assertInstanceOf(ValuesInterface::class, $result);
        return $result;
    }
}
