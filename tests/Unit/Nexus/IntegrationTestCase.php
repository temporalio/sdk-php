<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
use Nexus\Sdk\Exception\OperationException;
use Nexus\Sdk\Handler\OperationCancelDetails;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\OperationStartResult;
use Nexus\Sdk\Handler\SynchronousOperationHandler;
use Nexus\Sdk\Link;
use React\Promise\Deferred;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
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
 * Integration tests exercising the full path matching Go RoadRunner plugin format:
 * ServerRequest(options JSON) → Router → NexusTaskHandler → ServiceHandler → OperationHandler → EncodedValues
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
        $repository->add(\Nexus\Sdk\Handler\ServiceImplInstance::fromInstance($this->serviceImpl));

        $taskHandler = new \Temporal\Internal\Nexus\NexusTaskHandler($repository, $this->serializer, $this->dataConverter);

        $this->invokeRoute = new InvokeNexusOperation($taskHandler);
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

    public function testAsyncOperation(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'asyncEcho', 'test');
        $deferred = new Deferred();
        $this->invokeRoute->handle($request, [], $deferred);
        $result = $this->awaitResult($deferred);
        self::assertSame('async-token-test', $result);
    }

    // ── Operation error ──────────────────────────────────────────

    public function testOperationFailure(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'failOp', 'reason');
        $deferred = new Deferred();

        $this->expectException(NexusHandlerErrorException::class);
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
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('NOT_FOUND', $e->handlerError->getErrorType());
        }
    }

    public function testHandlerErrorForUnknownOperation(): void
    {
        $request = $this->makeInvokeRequest('EchoService', 'nonExistent', 'input');
        $deferred = new Deferred();
        try {
            $this->invokeRoute->handle($request, [], $deferred);
            $this->awaitResult($deferred);
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('NOT_FOUND', $e->handlerError->getErrorType());
        }
    }

    // ── Cancel operation ─────────────────────────────────────────

    public function testCancelOperation(): void
    {
        $request = $this->makeCancelRequest('EchoService', 'cancelOp', 'my-token');
        $deferred = new Deferred();
        $this->cancelRoute->handle($request, [], $deferred);
        // Should resolve without error
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
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('NOT_FOUND', $e->handlerError->getErrorType());
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
