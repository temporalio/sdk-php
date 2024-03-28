<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Connection;

interface ConnectionInterface
{
    public function isConnected(): bool;

    public function disconnect(): void;

    public function connect(float $timeout): void;
}
