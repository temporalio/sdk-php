<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\Common;

use PHPUnit\Framework\TestCase;
use Temporal\Client\Common\ClientContextTrait;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Support\DateInterval;

class ClientContextTraitTestCase extends TestCase
{
    public function testWithTimeout(): void
    {
        $mock = $this->createMock(ContextInterface::class);
        $mock->expects($this->once())
            ->method('withTimeout')
            ->with(1234, DateInterval::FORMAT_MILLISECONDS)
            ->willReturn($next = $this->createMock(ContextInterface::class));

        $before = $this->createClass($mock, $next);
        $client = $before->getClient();
        $after = $before->withTimeout(1.234);

        // Immutability check
        $this->assertNotSame($before, $after);
        // Client wasn't changed
        $this->assertSame($before->getClient(), $client);
        // In the new client, the context was changed
        $this->assertNotSame($before->getClient(), $after->getClient());
    }

    public function testWithDeadline(): void
    {
        $deadline = new \DateTimeImmutable();
        $mock = $this->createMock(ContextInterface::class);
        $mock->expects($this->once())
            ->method('withDeadline')
            ->with($deadline)
            ->willReturn($next = $this->createMock(ContextInterface::class));

        $before = $this->createClass($mock, $next);
        $client = $before->getClient();
        $after = $before->withDeadline($deadline);

        // Immutability check
        $this->assertNotSame($before, $after);
        // Client wasn't changed
        $this->assertSame($before->getClient(), $client);
        // In the new client, the context was changed
        $this->assertNotSame($before->getClient(), $after->getClient());
    }

    public function testWithRetryOptions(): void
    {
        $retry = RetryOptions::new()->withMaximumAttempts(123);
        $mock = $this->createMock(ContextInterface::class);
        $mock->expects($this->once())
            ->method('withRetryOptions')
            ->with($retry)
            ->willReturn($next = $this->createMock(ContextInterface::class));

        $before = $this->createClass($mock, $next);
        $client = $before->getClient();
        $after = $before->withRetryOptions($retry);

        // Immutability check
        $this->assertNotSame($before, $after);
        // Client wasn't changed
        $this->assertSame($before->getClient(), $client);
        // In the new client, the context was changed
        $this->assertNotSame($before->getClient(), $after->getClient());
    }

    public function testWithMetadata(): void
    {
        $metadata = ['authorization' => ['foo-bar-token']];
        $mock = $this->createMock(ContextInterface::class);
        $mock->expects($this->once())
            ->method('withMetadata')
            ->with($metadata)
            ->willReturn($next = $this->createMock(ContextInterface::class));

        $before = $this->createClass($mock, $next);
        $client = $before->getClient();
        $after = $before->withMetadata($metadata);

        // Immutability check
        $this->assertNotSame($before, $after);
        // Client wasn't changed
        $this->assertSame($before->getClient(), $client);
        // In the new client, the context was changed
        $this->assertNotSame($before->getClient(), $after->getClient());
    }

    private function createClass(ContextInterface $mock, ContextInterface $next): object
    {
        $client = self::createMock(ServiceClientInterface::class);
        $client->expects(self::once())
            ->method('getContext')
            ->willReturn($mock);
        $newClient = self::createMock(ServiceClientInterface::class);
        $newClient->expects(self::any())
            ->method('getContext')
            ->willReturn($next);
        $client->expects(self::once())
            ->method('withContext')
            ->with($next)
            ->willReturn($newClient);

        return new class($client) {
            use ClientContextTrait;
            public function __construct(ServiceClientInterface $client)
            {
                $this->client = $client;
            }

            public function getClient(): ServiceClientInterface
            {
                return $this->client;
            }
        };
    }
}
