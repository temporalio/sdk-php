<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\Interceptor;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\Interceptor\SystemInfoInterceptor;
use Temporal\Client\ServerCapabilities;
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
        $this->serviceClient
            ->expects($this->once())
            ->method('getServerCapabilities')
            ->willReturn(null);

        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );
    }

    public function testWithCapabilities(): void
    {
        $this->serviceClient
            ->expects($this->once())
            ->method('getServerCapabilities')
            ->willReturn(new ServerCapabilities());

        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );
    }

    public function testServiceClientException(): void
    {
        $exception = $this->createException(StatusCode::UNKNOWN);

        $this->serviceClient
            ->expects($this->once())
            ->method('getServerCapabilities')
            ->willThrowException($exception);

        $this->expectException(ServiceClientException::class);
        $this->interceptor->interceptCall(
            'foo',
            new \stdClass(),
            $this->createMock(ContextInterface::class),
            fn () => new \stdClass()
        );
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
