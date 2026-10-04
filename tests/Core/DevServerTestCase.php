<?php

declare(strict_types=1);

namespace Temporal\Tests\Core;

use PHPUnit\Framework\TestCase;

final class DevServerTestCase extends TestCase
{
    public function testServerStartsOnceAndAcceptsConnections(): void
    {
        $address = DevServer::address();

        self::assertSame($address, DevServer::address());
        $socket = \stream_socket_client('tcp://' . $address, $code, $message, 5);
        self::assertIsResource($socket);
        \fclose($socket);
    }
}
