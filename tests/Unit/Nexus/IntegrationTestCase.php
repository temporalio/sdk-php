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
use Temporal\Nexus\PayloadSerializer;
use Temporal\Tests\Unit\AbstractUnit;
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

    #[Operation]
    public function echoWithLinks(string $input): string;

    /** Reads caller-side links from $details->links and serialises them into the result. */
    #[Operation]
    public function reportCallerLinks(string $input): string;

    /** Reports whether $context->deadline was populated and roughly how far in the future it is. */
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

    public function echoWithLinks(string $input): string
    {
        Nexus::getCurrentContext()->links->add(
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
        $context = Nexus::getCurrentContext();
        if ($context->deadline === null) {
            return 'deadline:none';
        }
        $delta = $context->deadline->getTimestamp() - (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp();
        return "deadline:set;delta_seconds={$delta}";
    }
}

// ── Integration tests ─────────────────────────────────────────────

/**
 * Integration tests exercising the full path matching Go RoadRunner plugin format:
 * ServerRequest(options JSON) → Router → NexusTaskHandler → ServiceHandler → method-adapter → EncodedValues
 *
 * @group unit
 * @group nexus
 */
final class IntegrationTestCase extends AbstractUnit
{
    private InvokeNexusOperation $invokeRoute;
    private CancelNexusOperation $cancelRoute;
    private PayloadSerializer $serializer;
    private DataConverter $dataConverter;
    private EchoServiceImpl $serviceImpl;

    protected function setUp(): void
    {
        $this->dataConverter = DataConverter::createDefault();
        $this->serializer = new PayloadSerializer($this->dataConverter);

        $this->serviceImpl = new EchoServiceImpl();

        $repository = new \Temporal\Internal\Nexus\NexusServiceRepository();
        $repository->add(\Temporal\Nexus\Handler\ServiceImplInstance::fromInstance($this->serviceImpl));

        $taskHandler = new \Temporal\Internal\Nexus\NexusTaskHandler($repository, $this->serializer, $this->dataConverter);

        $this->invokeRoute = new InvokeNexusOperation($taskHandler, new \Temporal\Internal\Nexus\NexusInvocationRegistry());
        $this->cancelRoute = new CancelNexusOperation($taskHandler);
    }

    // ── Sync operation ───────────────────────────────────────────

    public function testSyncOperation(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'echo', 'hello');

        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);

        $result = $this->awaitResult($deferred);
        self::assertSame('echo:hello', $result);
    }

    public function testSyncOperationWithDifferentInputs(): void
    {
        foreach (['simple', 'with spaces', 'unicode: привет'] as $input) {
            $request = $this->makeInvokeRequest('EchoService', 'echo', $input);
            $deferred = new Deferred();
            $this->invokeRoute->handle($request, [], $deferred);
            $result = $this->awaitResult($deferred);
            self::assertSame("echo:{$input}", $result, "Failed for input: {$input}");
        }
    }

    // ── Async operation ──────────────────────────────────────────

    public function testAsyncOperationReturnsTokenTaggedPayload(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'asyncEcho', 'test');
        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);

        $values = $this->awaitDeferred($deferred);

        $payloads = $values->toPayloads();
        self::assertNotNull($payloads);
        self::assertSame(1, $payloads->getPayloads()->count());

        $payload = $payloads->getPayloads()[0];
        self::assertSame('async-token-test', $payload->getData());

        $meta = \iterator_to_array($payload->getMetadata());
        self::assertArrayHasKey(NexusTaskHandler::NEXUS_KIND_METADATA_KEY, $meta);
        self::assertSame(NexusTaskHandler::NEXUS_KIND_ASYNC, $meta[NexusTaskHandler::NEXUS_KIND_METADATA_KEY]);
    }

    // ── Operation error ──────────────────────────────────────────

    public function testOperationFailurePropagatesOperationException(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'failOp', 'reason');
        $deferred = new Deferred();

        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('fail:reason');
        $this->invokeRoute->handle($request, [], $deferred);
        $this->awaitResult($deferred);
    }

    // ── Handler error ────────────────────────────────────────────

    public function testHandlerErrorForUnknownService(): void
    {
        $request = $this->makeInvokeRequest('UnknownService', 'op', 'input');
        $deferred = new Deferred();
        try {
            $this->invokeRoute->handle($request, [], $deferred);
            $this->awaitResult($deferred);
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
            $this->awaitResult($deferred);
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
        $this->awaitDeferred($deferred);
        self::assertContains('my-token', $this->serviceImpl->canceledTokens);
    }

    public function testCancelUnknownService(): void
    {
        $request = $this->makeCancelRequest('UnknownService', 'op', 'token');
        $deferred = new Deferred();
        try {
            $this->cancelRoute->handle($request, [], $deferred);
            $this->awaitDeferred($deferred);
            self::fail('Expected HandlerException');
        } catch (NexusHandlerException $e) {
            self::assertSame(NexusErrorType::NotFound, $e->errorType);
        }
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
        $result = $this->awaitResult($deferred);
        self::assertSame('echo:test', $result);
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
        $result = $this->awaitResult($deferred);

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
        $deferred->promise()->then(null, function (\Throwable $e) use (&$error): void {
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
        $deferred->promise()->then(null, function (\Throwable $e) use (&$error): void {
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
        $request = $this->makeInvokeRequest('EchoService', 'reportCallerLinks', 'x');
        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $result = $this->awaitResult($deferred);

        self::assertStringContainsString('count=0', $result);
    }

    // ── Handler-side links → _rr_nexus_links metadata ────────────

    public function testHandlerAddedLinksAreSerializedIntoPayloadMetadata(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'echoWithLinks', 'hi');
        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);

        $values = $this->awaitDeferred($deferred);
        $payloads = $values->toPayloads();
        self::assertNotNull($payloads);
        self::assertCount(1, $payloads->getPayloads());

        $meta = $payloads->getPayloads()[0]->getMetadata();
        self::assertTrue(isset($meta[NexusTaskHandler::NEXUS_LINKS_METADATA_KEY]));

        $decoded = \json_decode((string) $meta[NexusTaskHandler::NEXUS_LINKS_METADATA_KEY], true);
        self::assertIsArray($decoded);
        self::assertCount(2, $decoded);
        self::assertSame('http://test.local/resource/1', $decoded[0]['url']);
        self::assertSame('test.Resource', $decoded[0]['type']);
        self::assertSame('http://test.local/resource/2', $decoded[1]['url']);
    }

    public function testNoLinksMeansNoMetadataKey(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'echo', 'hello');
        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);

        $values = $this->awaitDeferred($deferred);
        $payloads = $values->toPayloads();
        self::assertNotNull($payloads);
        $meta = $payloads->getPayloads()[0]->getMetadata();
        self::assertFalse(
            isset($meta[NexusTaskHandler::NEXUS_LINKS_METADATA_KEY]),
            '_rr_nexus_links must be omitted when the handler adds no links',
        );
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
        $result = $this->awaitResult($deferred);

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
        $result = $this->awaitResult($deferred);

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
        $result = $this->awaitResult($deferred);

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
        $result = $this->awaitResult($deferred);

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
        $result = $this->awaitResult($deferred);

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

    // ── Helpers ──────────────────────────────────────────────────

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

    private function awaitResult(Deferred $deferred): string
    {
        $values = $this->awaitDeferred($deferred);
        return $values->getValue(0, 'string');
    }

    private function awaitDeferred(Deferred $deferred): ValuesInterface
    {
        $result = null;
        $error = null;

        $deferred->promise()->then(
            function ($value) use (&$result): void { $result = $value; },
            function (\Throwable $e) use (&$error): void { $error = $e; },
        );

        if ($error !== null) {
            throw $error;
        }

        self::assertInstanceOf(ValuesInterface::class, $result);
        return $result;
    }
}
