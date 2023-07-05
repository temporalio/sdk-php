<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\Interceptor;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse\Capabilities;
use Temporal\Client\DTO\ServerCapabilities;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\Interceptor\SystemInfoInterceptor;
use Temporal\Exception\Client\ServiceClientException;

final class SystemInfoInterceptorTestCase extends TestCase
{
    private ServiceClient $serviceClient;
    private SystemInfoInterceptor $interceptor;

    protected function setUp(): void
    {
        $this->serviceClient = $this->createMock(ServiceClient::class);
        $this->interceptor = new SystemInfoInterceptor($this->serviceClient);
    }

    public function testWithoutCapabilities(): void
    {
        $this->assertFalse($this->isRequested());

        $this->serviceClient
            ->expects($this->once())
            ->method('getSystemInfo')
            ->willReturn(new GetSystemInfoResponse(['capabilities' => null]));

        $this->serviceClient
            ->expects($this->never())
            ->method('setServerCapabilities');

        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );

        $this->assertTrue($this->isRequested());
    }

    public function testWithCapabilities(): void
    {
        $this->assertFalse($this->isRequested());

        $this->serviceClient
            ->expects($this->once())
            ->method('getSystemInfo')
            ->willReturn(new GetSystemInfoResponse(
                [
                    'capabilities' => new Capabilities([
                        'signal_and_query_header' => true,
                        'internal_error_differentiation' => true
                    ])
                ]
            ));

        $this->serviceClient
            ->expects($this->once())
            ->method('setServerCapabilities')
            ->with($this->callback(
                fn (ServerCapabilities $capabilities) =>
                    $capabilities->isSignalAndQueryHeaderSupports() &&
                    $capabilities->isInternalErrorDifferentiation()
            ));

        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );

        $this->assertTrue($this->isRequested());
    }

    public function testRequestShouldBeExecutedOnce(): void
    {
        $this->assertFalse($this->isRequested());

        $this->serviceClient
            // it is important for this test
            ->expects($this->once())
            ->method('getSystemInfo')
            ->willReturn(new GetSystemInfoResponse(['capabilities' => null]));

        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );

        $this->assertTrue($this->isRequested());

        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );

        $this->assertTrue($this->isRequested());
    }

    public function testUnimplementedException(): void
    {
        $this->assertFalse($this->isRequested());

        $exception = $this->createException(StatusCode::UNIMPLEMENTED);

        $this->serviceClient
            ->expects($this->once())
            ->method('getSystemInfo')
            ->willThrowException($exception);

        $this->serviceClient
            ->expects($this->never())
            ->method('setServerCapabilities');

        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );

        $this->assertTrue($this->isRequested());
    }

    public function testServiceClientException(): void
    {
        $exception = $this->createException(StatusCode::UNKNOWN);

        $this->serviceClient
            ->expects($this->once())
            ->method('getSystemInfo')
            ->willThrowException($exception);

        $this->serviceClient
            ->expects($this->never())
            ->method('setServerCapabilities');

        $this->expectException(ServiceClientException::class);
        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );
    }

    private function isRequested(): bool
    {
        $ref = new \ReflectionProperty($this->interceptor, 'systemInfoRequested');
        $ref->setAccessible(true);

        return $ref->getValue($this->interceptor);
    }

    private function createException(int $code): ServiceClientException
    {
        return new class ($code) extends ServiceClientException {
            public function __construct(int $code)
            {
                $status = new \stdClass();
                $status->details = 'foo';
                $status->code = $code;

                parent::__construct($status);
            }
        };
    }
}
