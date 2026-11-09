<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC\Connection;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\GRPC\Connection\Connection;
use Temporal\Client\GRPC\Connection\ConnectionState;

final class ConnectionTestCase extends TestCase
{
    public function testConnectReturnsWhenChannelIsReadyAtDeadline(): void
    {
        $stub = self::stub(readyAfterWaits: 1);
        $connection = new Connection(static fn() => $stub);

        $connection->connect(0.0);

        self::assertTrue($connection->isConnected());
    }

    public function testConnectWaitsUntilChannelIsReady(): void
    {
        $stub = self::stub(readyAfterWaits: 3);
        $connection = new Connection(static fn() => $stub);

        $connection->connect(60.0);

        self::assertSame(3, $stub->waits);
    }

    public function testConnectInFiberReturnsWhenChannelIsReadyAtDeadline(): void
    {
        $stub = self::stub();
        $connection = new Connection(static fn() => $stub);
        $fiber = new \Fiber(static fn() => $connection->connect(0.0));

        $fiber->start();
        $stub->state = ConnectionState::Ready;
        $fiber->resume();

        self::assertTrue($fiber->isTerminated());
    }

    public function testConnectThrowsWhenChannelIsNotReadyAtDeadline(): void
    {
        $stub = self::stub();
        $connection = new Connection(static fn() => $stub);

        $this->expectExceptionMessage('Timeout exceeded');

        $connection->connect(0.0);
    }

    private static function stub(?int $readyAfterWaits = null): WorkflowServiceClient
    {
        return new class($readyAfterWaits) extends WorkflowServiceClient {
            public ConnectionState $state = ConnectionState::Connecting;
            public int $waits = 0;

            public function __construct(
                private readonly ?int $readyAfterWaits,
            ) {}

            public function getConnectivityState($try_to_connect = false): int
            {
                return $this->state->value;
            }

            public function waitForReady($timeout): bool
            {
                ++$this->waits;
                if ($this->waits === $this->readyAfterWaits) {
                    $this->state = ConnectionState::Ready;
                }

                return $this->state === ConnectionState::Ready;
            }

            public function close(): void {}
        };
    }
}
