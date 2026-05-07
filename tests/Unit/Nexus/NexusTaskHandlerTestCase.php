<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Google\Rpc\Code;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Exception\RetryBehavior;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Prototype\NexusServiceCollection;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;
use Temporal\Nexus\Internal\Failure\NexusFailureConverter;
use Temporal\Nexus\Link;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Tests\Unit\AbstractUnit;

#[Service]
interface TestGreetingService
{
    #[Operation]
    public function sayHello(string $name): string;

    #[AsyncOperation(output: 'string')]
    public function asyncOp(string $input): OperationInfo;

    #[Operation]
    public function failingOp(string $input): string;

    #[AsyncOperation(output: 'string')]
    public function cancelableOp(string $input): OperationInfo;

    #[Operation]
    public function shout(string $input): string;

    #[Operation]
    public function grpcFailingOp(string $input): string;

    #[Operation]
    public function appFailureOp(string $input): string;

    #[Operation]
    public function genericFailingOp(string $input): string;
}

class TestGreetingServiceImpl implements TestGreetingService
{
    public function sayHello(string $name): string
    {
        return "Hello, {$name}!";
    }

    public function asyncOp(string $input): OperationInfo
    {
        Nexus::getCurrentContext()->links->add(
            new Link('http://example.com/workflow/123', 'temporal.workflow'),
        );
        return new OperationInfo('op-token-123', OperationState::Running);
    }

    public function failingOp(string $input): string
    {
        throw OperationException::failed('Something went wrong');
    }

    public function cancelableOp(string $input): OperationInfo
    {
        return new OperationInfo('cancel-token-456', OperationState::Running);
    }

    #[OperationCancel(operation: 'cancelableOp')]
    public function cancelCancelableOp(string $token): void
    {
        if ($token === 'unknown') {
            throw HandlerException::create(ErrorType::NotFound, 'Not found');
        }
    }

    public function shout(string $input): string
    {
        return \strtoupper($input) . '!';
    }

    public function grpcFailingOp(string $input): string
    {
        $status = new \stdClass();
        $status->code = Code::NOT_FOUND;
        $status->details = 'workflow vanished';
        $status->metadata = [];
        throw new ServiceClientException($status);
    }

    public function appFailureOp(string $input): string
    {
        throw new ApplicationFailure(
            'business invariant violated',
            'BusinessError',
            true, // nonRetryable
            EncodedValues::empty(),
        );
    }

    public function genericFailingOp(string $input): string
    {
        throw new \RuntimeException('something blew up');
    }
}

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusTaskHandler::class)]
final class NexusTaskHandlerTestCase extends AbstractUnit
{
    private NexusTaskHandler $handler;
    private DataConverterInterface $dataConverter;

    public function testStartSyncOperation(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'sayHello', 'World');

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasSyncSuccess());

        $payload = $startResp->getSyncSuccess()->getPayload();
        self::assertNotNull($payload);

        $result = $this->dataConverter->fromPayload($payload, 'string');
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

    public function testTracebackIsAttachedByDefault(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'failingOp', 'input');

        $response = $this->handler->handleStartOperation($request);
        $failure = $response->getStartOperation()->getOperationError()->getFailure();

        self::assertNotSame('', $failure->getDetails());
        $details = \json_decode($failure->getDetails(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($details);
        self::assertSame('failed', $details['state']);

        $chain = $details[NexusFailureConverter::DETAILS_TRACEBACK_KEY];
        self::assertIsArray($chain);
        self::assertSame(OperationException::class, $chain[0]['type']);
        self::assertSame('Something went wrong', $chain[0]['message']);

        $meta = \iterator_to_array($failure->getMetadata());
        self::assertSame(NexusFailureConverter::OPERATION_ERROR_TYPE, $meta['type']);
    }

    public function testTracebackCanBeStrippedViaConstructorFlag(): void
    {
        $repository = self::buildRepository(new TestGreetingServiceImpl());
        $handler = new NexusTaskHandler(
            $repository,
            $this->dataConverter,
            includeTracebackInFailure: false,
        );

        $request = $this->buildStartRequest('TestGreetingService', 'failingOp', 'input');
        $response = $handler->handleStartOperation($request);
        $failure = $response->getStartOperation()->getOperationError()->getFailure();

        // Canonical envelope: details still carries `state` even without traceback.
        $details = \json_decode($failure->getDetails(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['state' => 'failed'], $details);

        $meta = \iterator_to_array($failure->getMetadata());
        self::assertSame(NexusFailureConverter::OPERATION_ERROR_TYPE, $meta['type']);
        self::assertSame('Something went wrong', $failure->getMessage());
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

    public function testGrpcServiceClientExceptionMapsToHandlerError(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'grpcFailingOp', 'input');

        try {
            $this->handler->handleStartOperation($request);
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('NOT_FOUND', $e->handlerError->getErrorType());
            self::assertInstanceOf(ServiceClientException::class, $e->getPrevious()->getPrevious());
        }
    }

    public function testNonRetryableApplicationFailureMapsToInternalNonRetryable(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'appFailureOp', 'input');

        try {
            $this->handler->handleStartOperation($request);
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('INTERNAL', $e->handlerError->getErrorType());
            self::assertSame(
                \Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_NON_RETRYABLE,
                $e->handlerError->getRetryBehavior(),
            );
        }
    }

    public function testGenericThrowableNeverEscapesAsRawException(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'genericFailingOp', 'input');

        try {
            $this->handler->handleStartOperation($request);
            self::fail('Expected NexusHandlerErrorException');
        } catch (NexusHandlerErrorException $e) {
            self::assertSame('INTERNAL', $e->handlerError->getErrorType());
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious()->getPrevious());
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
        $startReq->setPayload($this->dataConverter->toPayload('World'));

        $request = new Request();
        $request->setStartOperation($startReq);
        $request->setHeader(['content-type' => 'application/json']);

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
    }

    public function testShoutSyncOperation(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'shout', 'hello');

        $response = $this->handler->handleStartOperation($request);

        self::assertTrue($response->hasStartOperation());
        $startResp = $response->getStartOperation();
        self::assertTrue($startResp->hasSyncSuccess());

        self::assertSame('HELLO!', $this->decodeSyncStringResult($startResp));
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
        $startReq->setPayload($this->dataConverter->toPayload('World'));

        $request = new Request();
        $request->setStartOperation($startReq);

        $response = $this->handler->handleStartOperation($request);
        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
    }

    protected function setUp(): void
    {
        $this->dataConverter = DataConverter::createDefault();

        $this->handler = new NexusTaskHandler(
            self::buildRepository(new TestGreetingServiceImpl()),
            $this->dataConverter,
        );
    }

    /**
     * Build a {@see NexusServiceCollection} populated with the given service instances,
     * using the same Reader wiring as the Worker.
     */
    private static function buildRepository(object ...$instances): NexusServiceCollection
    {
        $reader = new NexusServiceReader(new AttributeReader());

        $collection = new NexusServiceCollection();
        foreach ($instances as $instance) {
            $prototype = $reader->fromClass(\get_class($instance))->withInstance($instance);
            $collection->add($prototype, false);
        }
        return $collection;
    }

    private function buildStartRequest(string $service, string $operation, string $input): Request
    {
        $startReq = new StartOperationRequest();
        $startReq->setService($service);
        $startReq->setOperation($operation);
        $startReq->setRequestId('test-request-id');
        $startReq->setPayload($this->dataConverter->toPayload($input));

        $request = new Request();
        $request->setStartOperation($startReq);
        return $request;
    }

    private function decodeSyncStringResult(
        \Temporal\Api\Nexus\V1\StartOperationResponse $startResp,
    ): string {
        $payload = $startResp->getSyncSuccess()->getPayload();
        self::assertNotNull($payload);

        return (string) $this->dataConverter->fromPayload($payload, 'string');
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
