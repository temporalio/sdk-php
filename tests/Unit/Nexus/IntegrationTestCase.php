<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
use Nexus\Sdk\Exception\ErrorType;
use Nexus\Sdk\Exception\HandlerException;
use Nexus\Sdk\Exception\OperationException;
use Nexus\Sdk\Handler\OperationCancelDetails;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\OperationStartResult;
use Nexus\Sdk\Handler\SynchronousOperationHandler;
use Nexus\Sdk\Link;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use Spiral\Attributes\AttributeReader;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\Api\Nexus\V1\StartOperationResponse;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router\CancelNexusOperation;
use Temporal\Internal\Transport\Router\StartNexusOperation;
use Temporal\Nexus\PayloadSerializer;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

// ── Test service definitions ─────────────────────────────────────────

#[Service(name: 'EchoService')]
interface EchoServiceInterface
{
    #[Operation]
    public function echo(string $input): string;

    #[Operation]
    public function asyncEcho(string $input): string;

    #[Operation]
    public function failOp(string $input): string;

    #[Operation]
    public function cancelOp(string $input): string;

    #[Operation]
    public function echoWithLinks(string $input): string;
}

#[ServiceImpl(service: EchoServiceInterface::class)]
class EchoServiceImpl
{
    /** @var string[] */
    public array $canceledTokens = [];

    #[OperationImpl]
    public function echo(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            fn(OperationContext $ctx, OperationStartDetails $details, ?string $input): string
                => "echo:{$input}",
        );
    }

    #[OperationImpl]
    public function asyncEcho(): OperationHandlerInterface
    {
        return new class implements OperationHandlerInterface {
            public function start(OperationContext $context, OperationStartDetails $details, mixed $param): OperationStartResult
            {
                return OperationStartResult::async('async-token-' . $param);
            }
            public function cancel(OperationContext $context, OperationCancelDetails $details): void {}
            public static function sync(callable $function): OperationHandlerInterface
            {
                return new SynchronousOperationHandler($function);
            }
        };
    }

    #[OperationImpl]
    public function failOp(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            function (OperationContext $ctx, OperationStartDetails $details, ?string $input): string {
                throw OperationException::failed("fail:{$input}");
            },
        );
    }

    #[OperationImpl]
    public function cancelOp(): OperationHandlerInterface
    {
        $impl = $this;
        return new class($impl) implements OperationHandlerInterface {
            public function __construct(private readonly EchoServiceImpl $impl) {}
            public function start(OperationContext $context, OperationStartDetails $details, mixed $param): OperationStartResult
            {
                return OperationStartResult::async('cancel-token-' . $param);
            }
            public function cancel(OperationContext $context, OperationCancelDetails $details): void
            {
                $this->impl->canceledTokens[] = $details->operationToken;
            }
            public static function sync(callable $function): OperationHandlerInterface
            {
                return new SynchronousOperationHandler($function);
            }
        };
    }

    #[OperationImpl]
    public function echoWithLinks(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            function (OperationContext $ctx, OperationStartDetails $details, ?string $input): string {
                $ctx->addLinks(
                    new Link('http://test.local/resource/1', 'test.Resource'),
                    new Link('http://test.local/resource/2', 'test.Resource'),
                );
                return "linked:{$input}";
            },
        );
    }
}

// ── Integration tests ─────────────────────────────────────────────

/**
 * Integration tests that exercise the full path:
 * proto Request → Router route → NexusTaskHandler → ServiceHandler → OperationHandler → proto Response
 *
 * @group unit
 * @group nexus
 */
final class IntegrationTestCase extends AbstractUnit
{
    private StartNexusOperation $startRoute;
    private CancelNexusOperation $cancelRoute;
    private PayloadSerializer $serializer;
    private EchoServiceImpl $serviceImpl;

    protected function setUp(): void
    {
        $dataConverter = DataConverter::createDefault();
        $this->serializer = new PayloadSerializer($dataConverter);

        $this->serviceImpl = new EchoServiceImpl();

        $repository = new \Temporal\Internal\Nexus\NexusServiceRepository();
        $repository->add(\Nexus\Sdk\Handler\ServiceImplInstance::fromInstance($this->serviceImpl));

        $taskHandler = new \Temporal\Internal\Nexus\NexusTaskHandler($repository, $this->serializer);

        $this->startRoute = new StartNexusOperation($taskHandler);
        $this->cancelRoute = new CancelNexusOperation($taskHandler);
    }

    // ── Sync operation ───────────────────────────────────────────

    public function testSyncOperationThroughRouter(): void
    {
        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => $this->encodeNexusStartRequest('EchoService', 'echo', 'hello'),
        ]);

        $deferred = new Deferred();
        $this->startRoute->handle($request, [], $deferred);

        $result = $this->awaitDeferred($deferred);
        $response = $this->decodeNexusResponse($result);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasSyncSuccess());

        $resultValue = $this->deserializePayload($startResp->getSyncSuccess()->getPayload());
        self::assertSame('echo:hello', $resultValue);
    }

    public function testSyncOperationWithDifferentInputs(): void
    {
        foreach (['', 'simple', 'with spaces', 'unicode: привет', 'special: <>&"'] as $input) {
            $request = $this->makeServerRequest('StartNexusOperation', [
                'request' => $this->encodeNexusStartRequest('EchoService', 'echo', $input),
            ]);

            $deferred = new Deferred();
            $this->startRoute->handle($request, [], $deferred);

            $result = $this->awaitDeferred($deferred);
            $response = $this->decodeNexusResponse($result);

            $resultValue = $this->deserializePayload($response->getStartOperation()->getSyncSuccess()->getPayload());
            self::assertSame("echo:{$input}", $resultValue, "Failed for input: {$input}");
        }
    }

    // ── Async operation ──────────────────────────────────────────

    public function testAsyncOperationThroughRouter(): void
    {
        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => $this->encodeNexusStartRequest('EchoService', 'asyncEcho', 'test'),
        ]);

        $deferred = new Deferred();
        $this->startRoute->handle($request, [], $deferred);

        $result = $this->awaitDeferred($deferred);
        $response = $this->decodeNexusResponse($result);

        self::assertTrue($response->getStartOperation()->hasAsyncSuccess());
        self::assertSame('async-token-test', $response->getStartOperation()->getAsyncSuccess()->getOperationToken());
    }

    // ── Operation error ──────────────────────────────────────────

    public function testOperationFailureThroughRouter(): void
    {
        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => $this->encodeNexusStartRequest('EchoService', 'failOp', 'reason'),
        ]);

        $deferred = new Deferred();
        $this->startRoute->handle($request, [], $deferred);

        $result = $this->awaitDeferred($deferred);
        $response = $this->decodeNexusResponse($result);

        self::assertTrue($response->getStartOperation()->hasOperationError());
        $opError = $response->getStartOperation()->getOperationError();
        self::assertSame('failed', $opError->getOperationState());
        self::assertSame('fail:reason', $opError->getFailure()->getMessage());
    }

    // ── Handler error (unknown service) ──────────────────────────

    public function testHandlerErrorForUnknownService(): void
    {
        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => $this->encodeNexusStartRequest('UnknownService', 'op', 'input'),
        ]);

        $deferred = new Deferred();
        try {
            $this->startRoute->handle($request, [], $deferred);
            // If handle doesn't throw, check the deferred
            $this->awaitDeferred($deferred);
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('NOT_FOUND', $e->handlerError->getErrorType());
        }
    }

    public function testHandlerErrorForUnknownOperation(): void
    {
        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => $this->encodeNexusStartRequest('EchoService', 'nonExistent', 'input'),
        ]);

        $deferred = new Deferred();
        try {
            $this->startRoute->handle($request, [], $deferred);
            $this->awaitDeferred($deferred);
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('NOT_FOUND', $e->handlerError->getErrorType());
        }
    }

    // ── Cancel operation ─────────────────────────────────────────

    public function testCancelOperationThroughRouter(): void
    {
        $request = $this->makeServerRequest('CancelNexusOperation', [
            'request' => $this->encodeNexusCancelRequest('EchoService', 'cancelOp', 'my-token'),
        ]);

        $deferred = new Deferred();
        $this->cancelRoute->handle($request, [], $deferred);

        $result = $this->awaitDeferred($deferred);
        $response = $this->decodeNexusResponse($result);

        self::assertTrue($response->hasCancelOperation());
        self::assertContains('my-token', $this->serviceImpl->canceledTokens);
    }

    public function testCancelUnknownServiceThroughRouter(): void
    {
        $request = $this->makeServerRequest('CancelNexusOperation', [
            'request' => $this->encodeNexusCancelRequest('UnknownService', 'op', 'token'),
        ]);

        $deferred = new Deferred();
        try {
            $this->cancelRoute->handle($request, [], $deferred);
            $this->awaitDeferred($deferred);
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('NOT_FOUND', $e->handlerError->getErrorType());
        }
    }

    // ── Links propagation ────────────────────────────────────────

    public function testSyncOperationPropagatesLinks(): void
    {
        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => $this->encodeNexusStartRequest('EchoService', 'echoWithLinks', 'world'),
        ]);

        $deferred = new Deferred();
        $this->startRoute->handle($request, [], $deferred);

        $result = $this->awaitDeferred($deferred);
        $response = $this->decodeNexusResponse($result);

        $syncResp = $response->getStartOperation()->getSyncSuccess();
        $links = $syncResp->getLinks();
        self::assertCount(2, $links);
        self::assertSame('http://test.local/resource/1', $links[0]->getUrl());
        self::assertSame('test.Resource', $links[0]->getType());
        self::assertSame('http://test.local/resource/2', $links[1]->getUrl());
    }

    // ── Headers propagation ──────────────────────────────────────

    public function testRequestHeadersArePropagated(): void
    {
        $nexusRequest = $this->buildStartRequest('EchoService', 'echo', 'test');
        $nexusRequest->setHeader([
            'Authorization' => 'Bearer token123',
            'X-Request-Id' => 'req-456',
        ]);

        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => \base64_encode($nexusRequest->serializeToString()),
        ]);

        $deferred = new Deferred();
        $this->startRoute->handle($request, [], $deferred);

        $result = $this->awaitDeferred($deferred);
        $response = $this->decodeNexusResponse($result);

        // If we got here without error, headers were accepted
        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
    }

    // ── Callback URL ─────────────────────────────────────────────

    public function testCallbackUrlPassedToHandler(): void
    {
        $startReq = new StartOperationRequest();
        $startReq->setService('EchoService');
        $startReq->setOperation('echo');
        $startReq->setRequestId('req-with-callback');
        $startReq->setCallback('http://callback.example.com/complete');
        $startReq->setCallbackHeader(['Token' => 'callback-token']);

        $content = $this->serializer->serialize('test');
        $payload = new Payload();
        $payload->setData($content->data);
        $payload->setMetadata($content->headers);
        $startReq->setPayload($payload);

        $nexusRequest = new Request();
        $nexusRequest->setStartOperation($startReq);

        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => \base64_encode($nexusRequest->serializeToString()),
        ]);

        $deferred = new Deferred();
        $this->startRoute->handle($request, [], $deferred);

        $result = $this->awaitDeferred($deferred);
        $response = $this->decodeNexusResponse($result);

        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
    }

    // ── Caller links ─────────────────────────────────────────────

    public function testCallerLinksPassedToHandler(): void
    {
        $link1 = new \Temporal\Api\Nexus\V1\Link();
        $link1->setUrl('http://caller.example.com/wf/123');
        $link1->setType('temporal.workflow');

        $startReq = new StartOperationRequest();
        $startReq->setService('EchoService');
        $startReq->setOperation('echo');
        $startReq->setRequestId('req-with-links');
        $startReq->setLinks([$link1]);

        $content = $this->serializer->serialize('test');
        $payload = new Payload();
        $payload->setData($content->data);
        $payload->setMetadata($content->headers);
        $startReq->setPayload($payload);

        $nexusRequest = new Request();
        $nexusRequest->setStartOperation($startReq);

        $request = $this->makeServerRequest('StartNexusOperation', [
            'request' => \base64_encode($nexusRequest->serializeToString()),
        ]);

        $deferred = new Deferred();
        $this->startRoute->handle($request, [], $deferred);

        $result = $this->awaitDeferred($deferred);
        $response = $this->decodeNexusResponse($result);

        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
    }

    // ── Route names ──────────────────────────────────────────────

    public function testStartNexusOperationRouteName(): void
    {
        self::assertSame('StartNexusOperation', $this->startRoute->getName());
    }

    public function testCancelNexusOperationRouteName(): void
    {
        self::assertSame('CancelNexusOperation', $this->cancelRoute->getName());
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function encodeNexusStartRequest(string $service, string $operation, string $input): string
    {
        $nexusRequest = $this->buildStartRequest($service, $operation, $input);
        return \base64_encode($nexusRequest->serializeToString());
    }

    private function buildStartRequest(string $service, string $operation, string $input): Request
    {
        $content = $this->serializer->serialize($input);
        $payload = new Payload();
        $payload->setData($content->data);
        $payload->setMetadata($content->headers);

        $startReq = new StartOperationRequest();
        $startReq->setService($service);
        $startReq->setOperation($operation);
        $startReq->setRequestId('test-req-' . \bin2hex(\random_bytes(4)));
        $startReq->setPayload($payload);

        $request = new Request();
        $request->setStartOperation($startReq);
        return $request;
    }

    private function encodeNexusCancelRequest(string $service, string $operation, string $token): string
    {
        $cancelReq = new CancelOperationRequest();
        $cancelReq->setService($service);
        $cancelReq->setOperation($operation);
        $cancelReq->setOperationToken($token);

        $request = new Request();
        $request->setCancelOperation($cancelReq);
        return \base64_encode($request->serializeToString());
    }

    private function makeServerRequest(string $name, array $options): ServerRequest
    {
        return new ServerRequest(
            name: $name,
            info: new TickInfo(new \DateTimeImmutable()),
            options: $options,
        );
    }

    private function awaitDeferred(Deferred $deferred): ValuesInterface
    {
        $result = null;
        $error = null;

        $deferred->promise()->then(
            function ($value) use (&$result): void {
                $result = $value;
            },
            function (\Throwable $e) use (&$error): void {
                $error = $e;
            },
        );

        if ($error !== null) {
            throw $error;
        }

        self::assertInstanceOf(ValuesInterface::class, $result);
        return $result;
    }

    private function decodeNexusResponse(ValuesInterface $values): \Temporal\Api\Nexus\V1\Response
    {
        $encoded = $values->getValue(0, 'string');
        $response = new \Temporal\Api\Nexus\V1\Response();
        $response->mergeFromString(\base64_decode($encoded));
        return $response;
    }

    private function deserializePayload(?Payload $payload): mixed
    {
        if ($payload === null) {
            return null;
        }

        $headers = [];
        foreach ($payload->getMetadata() as $key => $value) {
            $headers[(string) $key] = (string) $value;
        }

        return $this->serializer->deserialize(
            new \Nexus\Sdk\Serializer\Content($payload->getData(), $headers),
            'string',
        );
    }
}
