<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Common\EnvConfig\Exception\CodecNotSupportedException;

/**
 * Profile-level configuration for a Temporal client.
 *
 * Represents a single named configuration profile that can be loaded from TOML files or environment variables.
 * Profiles contain connection settings, TLS configuration, authentication credentials, and gRPC metadata.
 *
 * All properties are immutable (readonly) and empty strings are normalized to null during construction.
 *
 * @internal
 * @psalm-internal Temporal\Common\EnvConfig
 */
final class ConfigProfile
{
    /**
     * Server address in the format "host:port".
     *
     * @var non-empty-string|null
     */
    public readonly ?string $address;

    /**
     * Temporal namespace to use for operations.
     *
     * @var non-empty-string|null
     */
    public readonly ?string $namespace;

    /**
     * API key for Temporal Cloud authentication.
     *
     * @var non-empty-string|\Stringable|null
     */
    public readonly null|string|\Stringable $apiKey;

    /**
     * gRPC metadata headers to send with requests.
     * Keys are normalized to lowercase.
     *
     * @var array<non-empty-lowercase-string, list<string>>
     */
    public readonly array $grpcMeta;

    /**
     * Construct a new configuration profile.
     *
     * Empty strings for address, namespace, and apiKey are automatically converted to null.
     * gRPC metadata keys are normalized to lowercase per gRPC specification.
     *
     * @param string|null $address Server address (empty string converted to null)
     * @param string|null $namespace Namespace name (empty string converted to null)
     * @param string|\Stringable|null $apiKey API key (empty string converted to null)
     * @param ConfigTls|null $tlsConfig TLS/mTLS configuration
     * @param array<non-empty-string, string|list<string>> $grpcMeta gRPC metadata headers
     * @param ConfigCodec|null $codecConfig Remote codec configuration (NOT SUPPORTED - will throw exception if used)
     *
     * @throws CodecNotSupportedException If codec configuration is provided (not supported in PHP SDK)
     */
    public function __construct(
        ?string $address,
        ?string $namespace,
        null|string|\Stringable $apiKey,
        public readonly ?ConfigTls $tlsConfig = null,
        array $grpcMeta = [],
        public readonly ?ConfigCodec $codecConfig = null,
    ) {
        // Normalize empty strings to null
        $this->address = $address === '' ? null : $address;
        $this->namespace = $namespace === '' ? null : $namespace;
        $this->apiKey = $apiKey === '' ? null : $apiKey;

        // Normalize gRPC metadata keys to lowercase per gRPC spec
        $meta = [];
        foreach ($grpcMeta as $key => $value) {
            $meta[\strtolower($key)] = \is_array($value) ? $value : [$value];
        }
        $this->grpcMeta = $meta;

        // Validate codec is not configured (not supported in PHP SDK)
        $codecConfig?->endpoint === null && $codecConfig?->auth === null or throw new CodecNotSupportedException();
    }

    /**
     * Merge this profile with another profile, with the other profile's values taking precedence.
     *
     * Creates a new profile by combining settings from both profiles. Non-null values from the
     * provided config override values from this profile. TLS and codec configurations are deeply merged.
     * gRPC metadata arrays are merged with keys normalized to lowercase (per gRPC spec), with
     * the other profile's values replacing this profile's values for duplicate keys.
     *
     * @param self $config Profile to merge with (values from this take precedence)
     * @return self New merged profile
     */
    public function mergeWith(self $config): self
    {
        return new self(
            address: $config->address ?? $this->address,
            namespace: $config->namespace ?? $this->namespace,
            apiKey: $config->apiKey ?? $this->apiKey,
            tlsConfig: self::mergeTlsConfigs($this->tlsConfig, $config->tlsConfig),
            grpcMeta: self::mergeGrpcMeta($this->grpcMeta, $config->grpcMeta),
            codecConfig: self::mergeCodecConfigs($this->codecConfig, $config->codecConfig),
        );
    }

    /**
     * Convert this profile to ClientOptions.
     *
     * Creates a ClientOptions instance with the namespace from this profile.
     * Other ClientOptions properties (identity, queryRejectionCondition) use their default values.
     *
     * @return ClientOptions Configured client options
     */
    public function toClientOptions(): ClientOptions
    {
        $options = new ClientOptions();
        $this->namespace === null or $options = $options->withNamespace($this->namespace);

        return $options;
    }

    /**
     * Convert this profile to a configured ServiceClient.
     *
     * Creates a ServiceClient with proper TLS configuration and API key authentication.
     * If tlsConfig is present and not disabled, creates an SSL-enabled client with optional
     * mTLS support. If apiKey is present, configures authentication headers.
     *
     * @return ServiceClient Configured service client ready for use
     * @throws \InvalidArgumentException If address is not configured
     */
    public function toServiceClient(): ServiceClient
    {
        $this->address === null and throw new \InvalidArgumentException('Address is required to create ServiceClient');

        // Determine if TLS should be used
        $useTls = $this->tlsConfig !== null && !($this->tlsConfig->disabled ?? false);

        // Create client with or without TLS
        $client = $useTls
            ? ServiceClient::createSSL(
                address: $this->address,
                crt: $this->tlsConfig->rootCerts,
                clientKey: $this->tlsConfig->privateKey,
                clientPem: $this->tlsConfig->certChain,
                overrideServerName: $this->tlsConfig->serverName,
            )
            : ServiceClient::create($this->address);

        // Add API key if present
        $this->apiKey === null or $client = $client->withAuthKey($this->apiKey);

        // Add gRPC metadata support when Context API is available
        if ($this->grpcMeta !== []) {
            $context = $client->getContext();
            $context = $context->withMetadata(self::mergeGrpcMeta($context->getMetadata(), $this->grpcMeta));
            $client = $client->withContext($context);
        }

        return $client;
    }

    /**
     * Merge two TLS configurations with the second taking precedence.
     *
     * @param ConfigTls|null $to Base TLS configuration
     * @param ConfigTls|null $from TLS configuration to merge (takes precedence)
     * @return ConfigTls|null Merged TLS configuration or null if both are null
     */
    private static function mergeTlsConfigs(?ConfigTls $to, ?ConfigTls $from): ?ConfigTls
    {
        return match (true) {
            $to === null => $from,
            $from === null => $to,
            default => $to->mergeWith($from),
        };
    }

    /**
     * Merge two gRPC metadata arrays with lowercase key normalization.
     *
     * Keys are normalized to lowercase per gRPC specification. Values from the second array
     * replace values from the first array for duplicate keys (case-insensitive).
     *
     * @param array<non-empty-lowercase-string, list<string>> $to Base metadata
     * @param array<non-empty-lowercase-string, list<string>> $from Metadata to merge (overrides base)
     * @return array<non-empty-lowercase-string, list<string>> Merged metadata
     */
    private static function mergeGrpcMeta(array $to, array $from): array
    {
        $merged = $to;
        foreach ($from as $key => $values) {
            $lowerKey = \strtolower($key);
            $merged[$lowerKey] = $values;
        }
        return $merged;
    }

    /**
     * Merge two codec configurations with the second taking precedence.
     *
     * @param ConfigCodec|null $to Base codec configuration
     * @param ConfigCodec|null $from Codec configuration to merge (takes precedence)
     * @return ConfigCodec|null Merged codec configuration or null if both are null
     */
    private static function mergeCodecConfigs(?ConfigCodec $to, ?ConfigCodec $from): ?ConfigCodec
    {
        return match (true) {
            $to === null => $from,
            $from === null => $to,
            default => $to->mergeWith($from),
        };
    }
}
