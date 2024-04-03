<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse\Capabilities;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\GRPC\BaseClient;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClient;

class BaseClientTestCase extends TestCase
{
    public function testGetCapabilitiesUsesCache(): void
    {
        $client = $this->createClientMock();

        $capabilities0 = $client->getServerCapabilities();
        $capabilities1 = $client->getServerCapabilities();

        $this->assertTrue($capabilities0->supportsSchedules);
        $this->assertSame($capabilities0, $capabilities1);
    }

    public function testGetCapabilitiesClearsCache(): void
    {
        $client = $this->createClientMock();

        $capabilities0 = $client->getServerCapabilities();
        $client->getConnection()->disconnect();
        $capabilities1 = $client->getServerCapabilities();

        $this->assertTrue($capabilities0->supportsSchedules);
        $this->assertNotSame($capabilities0, $capabilities1);
    }

    public function testClose(): void
    {
        $client = $this->createClientMock(static fn() => new class extends WorkflowServiceClient {
            public function __construct()
            {
            }

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::TransientFailure->value;
            }

            public function close(): void
            {
            }
        });
        $client->close();

        $this->assertFalse($client->getConnection()->isConnected());
    }

    public function testGetContext(): void
    {
        $client = $this->createClientMock();
        $context = $client->getContext();

        $this->assertSame($context, $client->getContext());
    }

    public function testWithContext(): void
    {
        $client = $this->createClientMock();
        $context = $client->getContext();
        $context2 = $context->withTimeout(1.234);
        $client2 = $client->withContext($context2);

        $this->assertSame($context, $client->getContext());
        $this->assertSame($context2, $client2->getContext());
        $this->assertNotSame($client, $client2);
    }

    private function createClientMock(?callable $serviceClientFactory = null): BaseClient
    {
        return new class($serviceClientFactory ?? static fn() => new class extends WorkflowServiceClient {
            public function __construct()
            {
            }

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::Ready->value;
            }

            public function close(): void
            {
            }
        }) extends ServiceClient {
            public function getSystemInfo(
                GetSystemInfoRequest $arg,
                ContextInterface $ctx = null,
            ): GetSystemInfoResponse {
                return (new GetSystemInfoResponse())
                    ->setCapabilities((new Capabilities)->setSupportsSchedules(true))
                    ->setServerVersion('1.2.3');
            }
        };
    }
}
