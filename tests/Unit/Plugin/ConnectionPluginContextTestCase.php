<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Plugin\ConnectionPluginContext;

/**
 * @group unit
 * @group plugin
 */
class ConnectionPluginContextTestCase extends TestCase
{
    public function testGetServiceClientReturnsConstructorValue(): void
    {
        $serviceClient = $this->createMock(ServiceClientInterface::class);
        $context = new ConnectionPluginContext($serviceClient);

        self::assertSame($serviceClient, $context->getServiceClient());
    }

    public function testSetServiceClientReplacesValue(): void
    {
        $original = $this->createMock(ServiceClientInterface::class);
        $replacement = $this->createMock(ServiceClientInterface::class);

        $context = new ConnectionPluginContext($original);
        $context->setServiceClient($replacement);

        self::assertSame($replacement, $context->getServiceClient());
    }

    public function testSetServiceClientReturnsSelf(): void
    {
        $context = new ConnectionPluginContext(
            $this->createMock(ServiceClientInterface::class),
        );

        $result = $context->setServiceClient(
            $this->createMock(ServiceClientInterface::class),
        );

        self::assertSame($context, $result);
    }
}
