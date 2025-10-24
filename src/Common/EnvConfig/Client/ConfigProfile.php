<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

/**
 * Profile-level configuration for a client.
 *
 * @internal
 * @psalm-internal Temporal\Common\EnvConfig
 */
final class ConfigProfile
{
    public function __construct(
        public readonly ?string $address,
        public readonly ?string $namespace,
        public readonly null|string|\Stringable $apiKey,
        public readonly ?ConfigTls $tlsConfig = null,
        public readonly array $grpcMeta = [],
    ) {}
}
