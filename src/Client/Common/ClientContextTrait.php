<?php

declare(strict_types=1);

namespace Temporal\Client\Common;

use Temporal\Client\GRPC\ServiceClientInterface;
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
        /** @psalm-suppress InvalidOperand */
        $timeout *= 1000;
        $new->client = $new->client->withContext(
            $context->withTimeout((int) $timeout, DateInterval::FORMAT_MILLISECONDS),
        );

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

    public function withRetryOptions(RpcRetryOptions $options): static
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
