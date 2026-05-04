<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\DataConverter\DataConverter;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Tests\Nexus\Fixture\ServiceHandler\VoidServiceImpl;
use Temporal\Tests\Nexus\Support\BindNexusService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class ServiceHandlerEdgeCasesTest extends TestCase
{
    use BindNexusService;

    public function testNoInstancesThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No service instances defined');
        ServiceHandler::create(
            dataConverter: DataConverter::createDefault(),
            instances: [],
        );
    }

    public function testDuplicateServiceNames(): void
    {
        $instance = self::bindNexusService(new VoidServiceImpl());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Multiple instances registered for service name");
        ServiceHandler::create(
            dataConverter: DataConverter::createDefault(),
            instances: [$instance, $instance],
        );
    }

    public function testGetters(): void
    {
        $dataConverter = DataConverter::createDefault();
        $instance = self::bindNexusService(new VoidServiceImpl());

        $handler = ServiceHandler::create(
            dataConverter: $dataConverter,
            instances: [$instance],
        );

        self::assertSame($dataConverter, $handler->getDataConverter());
        self::assertCount(1, $handler->getInstances());
    }
}
