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
use Nexus\Sdk\Handler\ServiceImplInstance;
use Nexus\Sdk\Handler\SynchronousOperationFunctionInterface;
use Nexus\Sdk\Handler\SynchronousOperationHandler;
use Nexus\Sdk\Link;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Nexus\NexusServiceRepository;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Nexus\PayloadSerializer;
use Temporal\Tests\Unit\AbstractUnit;

#[Service]
interface TestGreetingService
{
    #[Operation]
    public function sayHello(string $name): string;

    #[Operation]
    public function asyncOp(string $input): string;

    #[Operation]
    public function failingOp(string $input): string;

    #[Operation]
    public function cancelableOp(string $input): string;

    #[Operation]
    public function shoutViaFunctor(string $input): string;

    #[Operation]
    public function shoutViaFromFunction(string $input): string;

    #[Operation]
    public function shoutViaFromCallable(string $input): string;
}

/**
 * @implements SynchronousOperationFunctionInterface<string, string>
 */
final class ShoutFunctor implements SynchronousOperationFunctionInterface
{
    public function __invoke(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $input,
    ): mixed {
        return \strtoupper((string) $input) . '!';
    }
}

#[ServiceImpl(service: TestGreetingService::class)]
class TestGreetingServiceImpl
{
    #[OperationImpl]
    public function sayHello(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            fn(OperationContext $ctx, OperationStartDetails $details, ?string $name): string
                => "Hello, {$name}!",
        );
    }

    #[OperationImpl]
    public function asyncOp(): OperationHandlerInterface
    {
        return new class implements OperationHandlerInterface {
            public function start(OperationContext $context, OperationStartDetails $details, mixed $param): OperationStartResult
            {
                $context->addLinks(new Link('http://example.com/workflow/123', 'temporal.workflow'));
                return OperationStartResult::async('op-token-123');
            }

            public function cancel(OperationContext $context, OperationCancelDetails $details): void {}

            public static function sync(callable $function): OperationHandlerInterface
            {
                return new SynchronousOperationHandler($function);
            }
        };
    }

    #[OperationImpl]
    public function failingOp(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            function (OperationContext $ctx, OperationStartDetails $details, ?string $input): string {
                throw OperationException::failed('Something went wrong');
            },
        );
    }

    #[OperationImpl]
    public function cancelableOp(): OperationHandlerInterface
    {
        return new class implements OperationHandlerInterface {
            public function start(OperationContext $context, OperationStartDetails $details, mixed $param): OperationStartResult
            {
                return OperationStartResult::async('cancel-token-456');
            }

            public function cancel(OperationContext $context, OperationCancelDetails $details): void
            {
                if ($details->operationToken === 'unknown') {
                    throw HandlerException::create(ErrorType::NotFound, 'Not found');
                }
            }

            public static function sync(callable $function): OperationHandlerInterface
            {
                return new SynchronousOperationHandler($function);
            }
        };
    }

    #[OperationImpl]
    public function shoutViaFunctor(): OperationHandlerInterface
    {
        // A named functor implementing SynchronousOperationFunctionInterface passed
        // directly into the handler's constructor (union param accepts the interface).
        return new SynchronousOperationHandler(new ShoutFunctor());
    }

    #[OperationImpl]
    public function shoutViaFromFunction(): OperationHandlerInterface
    {
        // Explicit factory for functor-style creation.
        return SynchronousOperationHandler::fromFunction(new ShoutFunctor());
    }

    #[OperationImpl]
    public function shoutViaFromCallable(): OperationHandlerInterface
    {
        // Explicit factory for callable-style creation.
        return SynchronousOperationHandler::fromCallable(
            static fn(OperationContext $ctx, OperationStartDetails $d, ?string $input): string
                => \strtoupper((string) $input) . '!',
        );
    }
}

/**
 * @group unit
 * @group nexus
 */
final class NexusTaskHandlerTestCase extends AbstractUnit
{
    private NexusTaskHandler $handler;
    private PayloadSerializer $serializer;

    protected function setUp(): void
    {
        $dataConverter = DataConverter::createDefault();
        $this->serializer = new PayloadSerializer($dataConverter);

        $repository = new NexusServiceRepository();
        $repository->add(ServiceImplInstance::fromInstance(new TestGreetingServiceImpl()));

        $this->handler = new NexusTaskHandler($repository, $this->serializer);
    }

    public function testStartSyncOperation(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'sayHello', 'World');

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasSyncSuccess());

        $payload = $startResp->getSyncSuccess()->getPayload();
        self::assertNotNull($payload);

        $result = $this->serializer->deserialize(
            new \Nexus\Sdk\Serializer\Content(
                $payload->getData(),
                \iterator_to_array($payload->getMetadata()),
            ),
            'string',
        );
        self::assertSame('Hello, World!', $result);
    }

    public function testStartAsyncOperation(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'asyncOp', 'input');

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasAsyncSuccess());

        $asyncResp = $startResp->getAsyncSuccess();
        self::assertSame('op-token-123', $asyncResp->getOperationToken());

        // Verify links were propagated
        $links = $asyncResp->getLinks();
        self::assertCount(1, $links);
        self::assertSame('http://example.com/workflow/123', $links[0]->getUrl());
        self::assertSame('temporal.workflow', $links[0]->getType());
    }

    public function testStartOperationWithOperationException(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'failingOp', 'input');

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasOperationError());

        $opError = $startResp->getOperationError();
        self::assertSame('failed', $opError->getOperationState());
        self::assertSame('Something went wrong', $opError->getFailure()->getMessage());
    }

    public function testStartOperationWithUnknownService(): void
    {
        $request = $this->buildStartRequest('NonExistentService', 'op', 'input');

        $this->expectException(NexusHandlerErrorException::class);
        $this->handler->handleStartOperation($request);
    }

    public function testCancelOperation(): void
    {
        $request = $this->buildCancelRequest('TestGreetingService', 'cancelableOp', 'cancel-token-456');

        $response = $this->handler->handleCancelOperation($request);

        self::assertTrue($response->hasCancelOperation());
    }

    public function testCancelOperationWithHandlerException(): void
    {
        $request = $this->buildCancelRequest('TestGreetingService', 'cancelableOp', 'unknown');

        $this->expectException(NexusHandlerErrorException::class);
        $this->handler->handleCancelOperation($request);
    }

    public function testCancelOperationWithUnknownService(): void
    {
        $request = $this->buildCancelRequest('NonExistentService', 'op', 'token');

        $this->expectException(NexusHandlerErrorException::class);
        $this->handler->handleCancelOperation($request);
    }

    public function testHandlerErrorContainsErrorType(): void
    {
        $request = $this->buildStartRequest('NonExistentService', 'op', 'input');

        try {
            $this->handler->handleStartOperation($request);
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('NOT_FOUND', $e->handlerError->getErrorType());
            self::assertNotEmpty($e->handlerError->getFailure()->getMessage());
        }
    }

    public function testStartOperationWithCallbackUrl(): void
    {
        $startReq = new StartOperationRequest();
        $startReq->setService('TestGreetingService');
        $startReq->setOperation('sayHello');
        $startReq->setRequestId('req-123');
        $startReq->setCallback('http://callback.example.com/complete');
        $startReq->setCallbackHeader(['Authorization' => 'Bearer token']);

        $payload = $this->serializer->serialize('World');
        $protoPayload = new Payload();
        $protoPayload->setData($payload->data);
        $protoPayload->setMetadata($payload->headers);
        $startReq->setPayload($protoPayload);

        $request = new Request();
        $request->setStartOperation($startReq);
        $request->setHeader(['content-type' => 'application/json']);

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
    }

    public function testSyncOperationViaFunctorConstructor(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'shoutViaFunctor', 'hello');

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasSyncSuccess());

        self::assertSame('HELLO!', $this->decodeSyncStringResult($startResp));
    }

    public function testSyncOperationViaFromFunctionFactory(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'shoutViaFromFunction', 'world');

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasSyncSuccess());

        self::assertSame('WORLD!', $this->decodeSyncStringResult($startResp));
    }

    public function testSyncOperationViaFromCallableFactory(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'shoutViaFromCallable', 'quiet');

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasSyncSuccess());

        self::assertSame('QUIET!', $this->decodeSyncStringResult($startResp));
    }

    public function testFunctorAndCallableProduceIdenticalResults(): void
    {
        $callableResp = $this->handler->handleStartOperation(
            $this->buildStartRequest('TestGreetingService', 'shoutViaFromCallable', 'same-input'),
        );
        $functorResp = $this->handler->handleStartOperation(
            $this->buildStartRequest('TestGreetingService', 'shoutViaFromFunction', 'same-input'),
        );

        self::assertSame(
            $this->decodeSyncStringResult($callableResp->getStartOperation()),
            $this->decodeSyncStringResult($functorResp->getStartOperation()),
        );
    }

    public function testStartOperationWithLinks(): void
    {
        $link = new \Temporal\Api\Nexus\V1\Link();
        $link->setUrl('http://caller.example.com/resource/1');
        $link->setType('caller.resource');

        $startReq = new StartOperationRequest();
        $startReq->setService('TestGreetingService');
        $startReq->setOperation('sayHello');
        $startReq->setRequestId('req-456');
        $startReq->setLinks([$link]);

        $payload = $this->serializer->serialize('World');
        $protoPayload = new Payload();
        $protoPayload->setData($payload->data);
        $protoPayload->setMetadata($payload->headers);
        $startReq->setPayload($protoPayload);

        $request = new Request();
        $request->setStartOperation($startReq);

        $response = $this->handler->handleStartOperation($request);
        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
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
        $startReq->setRequestId('test-request-id');
        $startReq->setPayload($payload);

        $request = new Request();
        $request->setStartOperation($startReq);
        return $request;
    }

    private function decodeSyncStringResult(
        \Temporal\Api\Nexus\V1\StartOperationResponse $startResp,
    ): string {
        $payload = $startResp->getSyncSuccess()->getPayload();
        self::assertNotNull($payload);

        $result = $this->serializer->deserialize(
            new \Nexus\Sdk\Serializer\Content(
                $payload->getData(),
                \iterator_to_array($payload->getMetadata()),
            ),
            'string',
        );

        return (string) $result;
    }

    private function buildCancelRequest(string $service, string $operation, string $token): Request
    {
        $cancelReq = new CancelOperationRequest();
        $cancelReq->setService($service);
        $cancelReq->setOperation($operation);
        $cancelReq->setOperationToken($token);

        $request = new Request();
        $request->setCancelOperation($cancelReq);
        return $request;
    }
}
