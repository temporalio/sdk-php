<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Connection;

interface ConnectionInterface
{
    public function getState(bool $tryToConnect = false): ConnectionState;

    /**
     * @param int<0, max> $timeout in microseconds
     *
     * @return bool true if channel is ready
     * @throws \Exception if channel is in FATAL_ERROR state
     */
    public function waitForReady(int $timeout): bool;

    public function close(): void;
}
