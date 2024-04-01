<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Common\RetryOptions;
use Temporal\Internal\Support\DateInterval;

/**
 * The trait provides methods to configure context in ServiceClientInterface.
 *
 * @internal
 */
trait ClientContextTrait
{
    private ServiceClientInterface $client;

    /**
     * All the service client calls will be made with the specified timeout.
     *
     * @param float $timeout in seconds
     */
    public function withTimeout(float $timeout): static
    {
        $new = clone $this;
        $context = $new->client->getContext();
        // Convert to milliseconds
        $timeout *= 1000;
        $new->client = $new->client->withContext($context->withTimeout($timeout, DateInterval::FORMAT_MILLISECONDS));
        return $new;
    }

    /**
     * Set the deadline for any service requests
     */
    public function withDeadline(\DateTimeInterface $deadline): static
    {
        $new = clone $this;
        $context = $new->client->getContext();
        $new->client = $new->client->withContext($context->withDeadline($deadline));
        return $new;
    }

    public function withRetryOptions(RetryOptions $options): static
    {
        $new = clone $this;
        $context = $new->client->getContext();
        $new->client = $new->client->withContext($context->withRetryOptions($options));
        return $new;
    }

    /**
     * A metadata map to send to the server
     *
     * @param array<string, array<string>> $metadata
     */
    public function withMetadata(array $metadata): static
    {
        $new = clone $this;
        $context = $new->client->getContext();
        $new->client = $new->client->withContext($context->withMetadata($metadata));
        return $new;
    }
}
