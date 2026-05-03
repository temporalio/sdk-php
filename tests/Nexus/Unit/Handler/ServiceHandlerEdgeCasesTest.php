<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Handler\Internal\ServiceImplInstance;
use Temporal\Tests\Nexus\Fixture\Serializer\StringOnlySerializer;
use Temporal\Tests\Nexus\Fixture\ServiceHandler\VoidServiceImpl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class ServiceHandlerEdgeCasesTest extends TestCase
{
    public function testNoInstancesThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No service instances defined');
        ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [],
        );
    }

    public function testDuplicateServiceNames(): void
    {
        $instance = ServiceImplInstance::fromInstance(new VoidServiceImpl());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Multiple instances registered for service name");
        ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [$instance, $instance],
        );
    }

    public function testGetters(): void
    {
        $serializer = new StringOnlySerializer();
        $instance = ServiceImplInstance::fromInstance(new VoidServiceImpl());

        $handler = ServiceHandler::create(
            serializer: $serializer,
            instances: [$instance],
        );

        self::assertSame($serializer, $handler->getSerializer());
        self::assertCount(1, $handler->getInstances());
        self::assertCount(0, $handler->getOperationMiddlewares());
    }
}
