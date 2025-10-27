<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

/**
 * Profile-level configuration for a client.
 *
 * This class holds the configuration as loaded from a file or environment.
 *
 * @internal
 * @psalm-internal Temporal\Common\EnvConfig
 */
final class ConfigProfile
{
    /** @var array<non-empty-lowercase-string, string> */
    public readonly array $grpcMeta;

    public function __construct(
        public readonly ?string $address,
        public readonly ?string $namespace,
        public readonly null|string|\Stringable $apiKey,
        public readonly ?ConfigTls $tlsConfig = null,
        array $grpcMeta = [],
    ) {
        // Keys to strtolower
        $meta = [];
        foreach ($grpcMeta as $key => $value) {
            $meta[\strtolower($key)] = $value;
        }
        $this->grpcMeta = $meta;
    }

    public function mergeWith(self $config): self
    {
        return new self(
            address: $config->address ?? $this->address,
            namespace: $config->namespace ?? $this->namespace,
            apiKey: $config->apiKey ?? $this->apiKey,
            tlsConfig: self::mergeTlsConfigs($this->tlsConfig, $config->tlsConfig),
            grpcMeta: \array_merge($this->grpcMeta, $config->grpcMeta),
        );
    }

    private static function mergeTlsConfigs(?ConfigTls $to, ?ConfigTls $from): ?ConfigTls
    {
        return match (true) {
            $to === null => $from,
            $from === null => $to,
            default => $to->mergeWith($from),
        };
    }
}
