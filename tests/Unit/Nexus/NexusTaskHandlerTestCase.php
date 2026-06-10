<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Google\Rpc\Code;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Exception\RetryBehavior;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Prototype\NexusServiceCollection;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\Link;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Nexus\NexusOperationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;

#[Service]
interface TestGreetingService
{
    #[Operation]
    public function sayHello(string $name): string;

    #[AsyncOperation(output: 'string', input: 'string')]
    public function asyncOp(): GreetingAsyncOpHandler;

    #[Operation]
    public function failingOp(string $input): string;

    #[Operation]
    public function richCauseFailingOp(string $input): string;

    #[AsyncOperation(output: 'string', input: 'string')]
    public function cancelableOp(): GreetingCancelableOpHandler;

    #[Operation]
    public function shout(string $input): string;

    #[Operation]
    public function grpcFailingOp(string $input): string;

    #[Operation]
    public function appFailureOp(string $input): string;

    #[Operation]
    public function genericFailingOp(string $input): string;

    #[Operation]
    public function deadlineEchoOp(string $input): string;
}

class TestGreetingServiceImpl implements TestGreetingService
{
    /** @var array<string, string> Headers seen by the most recent start dispatch. */
    public static array $capturedStartHeaders = [];

    /** @var array<string, string> Headers seen by the most recent cancel dispatch. */
    public static array $capturedCancelHeaders = [];

    /** @var string|null Namespace seen by the most recent start dispatch. */
    public static ?string $capturedStartNamespace = null;

    /** @var string|null Task queue seen by the most recent start dispatch. */
    public static ?string $capturedStartTaskQueue = null;

    public function sayHello(string $name): string
    {
        self::$capturedStartHeaders = Nexus::getCurrentOperationContext()->headers->all();
        if (Nexus::getCurrentContext()->operation !== null) {
            self::$capturedStartNamespace = Nexus::getOperationContext()->namespace;
            self::$capturedStartTaskQueue = Nexus::getOperationContext()->taskQueue;
        }
        return "Hello, {$name}!";
    }

    public function asyncOp(): GreetingAsyncOpHandler
    {
        return new GreetingAsyncOpHandler();
    }

    public function failingOp(string $input): string
    {
        throw OperationException::failed('Something went wrong');
    }

    public function richCauseFailingOp(string $input): string
    {
        throw OperationException::failed(
            'outer-failure',
            new ApplicationFailure(
                'inner-detail',
                'CustomBusinessType',
                false,
                EncodedValues::fromValues(['marker']),
            ),
        );
    }

    public function cancelableOp(): GreetingCancelableOpHandler
    {
        return new GreetingCancelableOpHandler();
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

    public function deadlineEchoOp(string $input): string
    {
        $deadline = Nexus::getCurrentOperationContext()->deadline;
        return $deadline === null ? 'none' : $deadline->format(\DATE_ATOM);
    }
}

final class GreetingAsyncOpHandler implements OperationHandlerInterface
{
    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        Nexus::getCurrentOperationContext()->links->add(
            new Link('http://example.com/workflow/123', 'temporal.workflow'),
        );
        return OperationStartResult::async(new OperationInfo('op-token-123', OperationState::Running));
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {}
}

final class GreetingCancelableOpHandler implements OperationHandlerInterface
{
    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        return OperationStartResult::async(new OperationInfo('cancel-token-456', OperationState::Running));
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        TestGreetingServiceImpl::$capturedCancelHeaders = Nexus::getCurrentOperationContext()->headers->all();
        if ($details->operationToken === 'unknown') {
            throw HandlerException::create(ErrorType::NotFound, 'Not found');
        }
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
    private EnvironmentInterface $env;

    public function testStartSyncOperation(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'sayHello', 'World');

        $response = $this->handler->handleStartOperation($request, new NexusOperationContext());

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

        $response = $this->handler->handleStartOperation($request, new NexusOperationContext());

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

        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('Something went wrong');
        $this->handler->handleStartOperation($request, new NexusOperationContext());
    }

    public function testStartOperationPropagatesOperationExceptionCauseChain(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'richCauseFailingOp', 'input');

        try {
            $this->handler->handleStartOperation($request, new NexusOperationContext());
            self::fail('Expected OperationException');
        } catch (OperationException $e) {
            self::assertSame('outer-failure', $e->getMessage());

            $cause = $e->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $cause);
            self::assertSame('CustomBusinessType', $cause->getType());
            self::assertSame('inner-detail', $cause->getOriginalMessage());
            self::assertSame('marker', $cause->getDetails()->getValue(0, 'string'));
        }
    }

    public function testStartOperationWithUnknownService(): void
    {
        $request = $this->buildStartRequest('NonExistentService', 'op', 'input');

        $this->expectException(HandlerException::class);
        $this->handler->handleStartOperation($request, new NexusOperationContext());
    }

    public function testCancelOperation(): void
    {
        $request = $this->buildCancelRequest('TestGreetingService', 'cancelableOp', 'cancel-token-456');

        $response = $this->handler->handleCancelOperation($request, new NexusOperationContext());

        self::assertTrue($response->hasCancelOperation());
    }

    public function testCancelOperationWithHandlerException(): void
    {
        $request = $this->buildCancelRequest('TestGreetingService', 'cancelableOp', 'unknown');

        $this->expectException(HandlerException::class);
        $this->handler->handleCancelOperation($request, new NexusOperationContext());
    }

    public function testCancelOperationWithUnknownService(): void
    {
        $request = $this->buildCancelRequest('NonExistentService', 'op', 'token');

        $this->expectException(HandlerException::class);
        $this->handler->handleCancelOperation($request, new NexusOperationContext());
    }

    public function testCancelOperationWithEmptyTokenIsBadRequest(): void
    {
        $request = $this->buildCancelRequest('TestGreetingService', 'cancelableOp', '');

        try {
            $this->handler->handleCancelOperation($request, new NexusOperationContext());
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::BadRequest, $e->errorType);
            self::assertSame(RetryBehavior::NonRetryable, $e->retryBehavior);
        }
    }

    public function testCancelOperationPropagatesHeadersToContext(): void
    {
        $request = $this->buildCancelRequest('TestGreetingService', 'cancelableOp', 'cancel-token-456', [
            'X-Nexus-Trace-Id' => 'trace-1',
            'Authorization' => 'Bearer xyz',
        ]);

        $this->handler->handleCancelOperation($request, new NexusOperationContext());

        // OperationContext lowercases header keys on construction.
        self::assertSame('trace-1', TestGreetingServiceImpl::$capturedCancelHeaders['x-nexus-trace-id'] ?? null);
        self::assertSame('Bearer xyz', TestGreetingServiceImpl::$capturedCancelHeaders['authorization'] ?? null);
    }

    public function testStartOperationPropagatesHeadersToContext(): void
    {
        $startReq = new StartOperationRequest();
        $startReq->setService('TestGreetingService');
        $startReq->setOperation('sayHello');
        $startReq->setRequestId('req-h1');
        $startReq->setPayload($this->dataConverter->toPayload('World'));

        $request = new Request();
        $request->setStartOperation($startReq);
        $request->setHeader(['X-Nexus-Trace-Id' => 'trace-2']);

        $this->handler->handleStartOperation($request, new NexusOperationContext());

        self::assertSame('trace-2', TestGreetingServiceImpl::$capturedStartHeaders['x-nexus-trace-id'] ?? null);
    }

    public function testStartOperationUsesWireNamespace(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'sayHello', 'World');

        $operationContext = new NexusOperationContext();
        $operationContext->namespace = 'wire-ns';
        $operationContext->taskQueue = 'wire-tq';
        $this->handler->handleStartOperation($request, $operationContext);

        self::assertSame('wire-ns', TestGreetingServiceImpl::$capturedStartNamespace);
    }

    public function testStartOperationHasNoNamespaceWhenWireAbsent(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'sayHello', 'World');

        $this->handler->handleStartOperation($request, new NexusOperationContext());

        self::assertNull(TestGreetingServiceImpl::$capturedStartNamespace);
    }

    public function testStartOperationUsesWireTaskQueue(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'sayHello', 'World');

        $operationContext = new NexusOperationContext();
        $operationContext->namespace = 'wire-ns';
        $operationContext->taskQueue = 'wire-tq';
        $this->handler->handleStartOperation($request, $operationContext);

        self::assertSame('wire-tq', TestGreetingServiceImpl::$capturedStartTaskQueue);
    }

    public function testStartOperationHasNoTaskQueueWhenWireAbsent(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'sayHello', 'World');

        $this->handler->handleStartOperation($request, new NexusOperationContext());

        self::assertNull(TestGreetingServiceImpl::$capturedStartTaskQueue);
    }

    public function testHandlerErrorContainsErrorType(): void
    {
        $request = $this->buildStartRequest('NonExistentService', 'op', 'input');

        try {
            $this->handler->handleStartOperation($request, new NexusOperationContext());
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::NotFound, $e->errorType);
            self::assertNotEmpty($e->getMessage());
        }
    }

    public function testGrpcServiceClientExceptionMapsToHandlerError(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'grpcFailingOp', 'input');

        try {
            $this->handler->handleStartOperation($request, new NexusOperationContext());
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::NotFound, $e->errorType);
            self::assertInstanceOf(ServiceClientException::class, $e->getPrevious());
        }
    }

    public function testNonRetryableApplicationFailureMapsToInternalNonRetryable(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'appFailureOp', 'input');

        try {
            $this->handler->handleStartOperation($request, new NexusOperationContext());
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::Internal, $e->errorType);
            self::assertSame(RetryBehavior::NonRetryable, $e->retryBehavior);
        }
    }

    public function testGenericThrowableNeverEscapesAsRawException(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'genericFailingOp', 'input');

        try {
            $this->handler->handleStartOperation($request, new NexusOperationContext());
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::Internal, $e->errorType);
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }
    }

    public function testStartOperationWithMalformedTimeoutHeaderIsIgnored(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'deadlineEchoOp', 'input');
        $request->setHeader(['Request-Timeout' => 'not-a-duration']);

        $response = $this->handler->handleStartOperation($request, new NexusOperationContext());

        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
        self::assertSame('none', $this->decodeSyncStringResult($response->getStartOperation()));
    }

    public function testStartOperationWithValidTimeoutHeaderSetsDeadline(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'deadlineEchoOp', 'input');
        $request->setHeader(['Request-Timeout' => '30s']);

        $response = $this->handler->handleStartOperation($request, new NexusOperationContext());

        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
        self::assertNotSame('none', $this->decodeSyncStringResult($response->getStartOperation()));
    }

    public function testStartOperationWithAbsentTimeoutHeaderHasNoDeadline(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'deadlineEchoOp', 'input');

        $response = $this->handler->handleStartOperation($request, new NexusOperationContext());

        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
        self::assertSame('none', $this->decodeSyncStringResult($response->getStartOperation()));
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

        $response = $this->handler->handleStartOperation($request, new NexusOperationContext());

        self::assertTrue($response->hasStartOperation());
        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
    }

    public function testShoutSyncOperation(): void
    {
        $request = $this->buildStartRequest('TestGreetingService', 'shout', 'hello');

        $response = $this->handler->handleStartOperation($request, new NexusOperationContext());

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

        $response = $this->handler->handleStartOperation($request, new NexusOperationContext());
        self::assertTrue($response->getStartOperation()->hasSyncSuccess());
    }

    protected function setUp(): void
    {
        $this->dataConverter = DataConverter::createDefault();
        $this->env = new Environment();

        TestGreetingServiceImpl::$capturedStartHeaders = [];
        TestGreetingServiceImpl::$capturedCancelHeaders = [];
        TestGreetingServiceImpl::$capturedStartNamespace = null;
        TestGreetingServiceImpl::$capturedStartTaskQueue = null;

        $this->handler = new NexusTaskHandler(
            self::buildRepository(new TestGreetingServiceImpl()),
            $this->dataConverter,
            $this->env,
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

    /**
     * @param array<string, string> $headers
     */
    private function buildCancelRequest(string $service, string $operation, string $token, array $headers = []): Request
    {
        $cancelReq = new CancelOperationRequest();
        $cancelReq->setService($service);
        $cancelReq->setOperation($operation);
        $cancelReq->setOperationToken($token);

        $request = new Request();
        $request->setCancelOperation($cancelReq);
        if ($headers !== []) {
            $request->setHeader($headers);
        }
        return $request;
    }
}
