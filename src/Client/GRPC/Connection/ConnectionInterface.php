<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Connection;

interface ConnectionInterface
{
    public function getState(bool $tryToConnect = false): ConnectionState;

    /**
     * Wait for the channel to be ready.
     *
     * @param float $timeout in seconds
     *
     * @return bool true if channel is ready
     * @throws \Exception if channel is in FATAL_ERROR state
     */
    public function waitForReady(float $timeout): bool;

    public function close(): void;
}
