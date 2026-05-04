<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationErrorFailure;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Internal\Failure\NexusFailureConverter;
use Temporal\Nexus\Handler\Internal\ServiceImplInstance;
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
use Temporal\Internal\Nexus\NexusServiceRepository;
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
}

/**
 * @group unit
 * @group nexus
 */
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
        self::assertSame(OperationErrorFailure::TYPE, $meta['type']);
    }

    public function testTracebackCanBeStrippedViaConstructorFlag(): void
    {
        $repository = new NexusServiceRepository();
        $repository->add(ServiceImplInstance::fromInstance(new TestGreetingServiceImpl()));
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
        self::assertSame(OperationErrorFailure::TYPE, $meta['type']);
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

        $repository = new NexusServiceRepository();
        $repository->add(ServiceImplInstance::fromInstance(new TestGreetingServiceImpl()));

        $this->handler = new NexusTaskHandler($repository, $this->dataConverter);
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
