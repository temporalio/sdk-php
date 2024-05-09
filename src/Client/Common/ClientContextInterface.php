<?php

declare(strict_types=1);

namespace Temporal\Client\Common;

use Temporal\Common\RetryOptions;

interface ClientContextInterface
{
    /**
     * All the service client calls will be made with the specified timeout.
     *
     * @param float $timeout in seconds
     */
    public function withTimeout(float $timeout): static;

    /**
     * Set the deadline for any service requests
     */
    public function withDeadline(\DateTimeInterface $deadline): static;

    public function withRetryOptions(RpcRetryOptions $options): static;

    /**
     * A metadata map to send to the server
     *
     * @param array<string, array<string>> $metadata
     */
    public function withMetadata(array $metadata): static;
}
