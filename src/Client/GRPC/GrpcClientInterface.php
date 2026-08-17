<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Client\GRPC\Connection\ConnectionInterface;

interface GrpcClientInterface
{
    public function getContext(): ContextInterface;

    public function withContext(ContextInterface $context): static;

    public function withAuthKey(\Stringable|string $key): static;

    public function getConnection(): ConnectionInterface;

    /**
     * Close the communication channel associated with this stub.
     */
    public function close(): void;
}
