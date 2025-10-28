<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

/**
 * Remote codec configuration.
 *
 * Specifies endpoint and authentication for remote data encoding/decoding.
 * Remote codecs allow offloading payload encoding/decoding to an external service.
 *
 * @internal
 */
final class ConfigCodec
{
    /**
     * @param non-empty-string|null $endpoint Endpoint URL for the remote codec service
     * @param non-empty-string|null $auth Authorization header value for codec authentication
     */
    public function __construct(
        public readonly ?string $endpoint = null,
        public readonly ?string $auth = null,
    ) {}

    /**
     * Merge this codec config with another, with the other config's values taking precedence.
     *
     * @param self $from Codec config to merge (values from this take precedence)
     * @return self New merged codec config
     */
    public function mergeWith(self $from): self
    {
        return new self(
            endpoint: $from->endpoint ?? $this->endpoint,
            auth: $from->auth ?? $this->auth,
        );
    }
}
