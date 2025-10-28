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
    /** @var non-empty-string|null $endpoint Endpoint URL for the remote codec service */
    public readonly ?string $endpoint;

    /** @var non-empty-string|null $auth Authorization header value for codec authentication */
    public readonly ?string $auth;

    /**
     * @param string|null $endpoint Endpoint URL for the remote codec service
     * @param string|null $auth Authorization header value for codec authentication
     */
    public function __construct(
        ?string $endpoint = null,
        ?string $auth = null,
    ) {
        $this->auth = $auth === '' ? null : $auth;
        $this->endpoint = $endpoint === '' ? null : $endpoint;
    }

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
