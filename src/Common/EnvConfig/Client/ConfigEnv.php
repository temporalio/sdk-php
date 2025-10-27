<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

use Temporal\Common\EnvConfig\EnvProvider;

/**
 * Environment variable configuration parser for Temporal client.
 *
 * Reads Temporal client configuration from environment variables following
 * the naming convention: TEMPORAL_* (e.g., TEMPORAL_ADDRESS, TEMPORAL_NAMESPACE).
 *
 * Supported environment variables:
 * - TEMPORAL_ADDRESS - Temporal server address (host:port)
 * - TEMPORAL_NAMESPACE - Temporal namespace
 * - TEMPORAL_API_KEY - API key for authentication
 * - TEMPORAL_PROFILE - Active profile name
 * - TEMPORAL_CONFIG_FILE - Path to TOML configuration file
 * - TEMPORAL_TLS - Enable/disable TLS (boolean: true/false, 1/0, yes/no, on/off)
 * - TEMPORAL_TLS_CLIENT_CERT_PATH - Path to client certificate file
 * - TEMPORAL_TLS_CLIENT_CERT_DATA - Client certificate data (PEM format)
 * - TEMPORAL_TLS_CLIENT_KEY_PATH - Path to client private key file
 * - TEMPORAL_TLS_CLIENT_KEY_DATA - Client private key data (PEM format)
 * - TEMPORAL_TLS_SERVER_CA_CERT_PATH - Path to server CA certificate file
 * - TEMPORAL_TLS_SERVER_CA_CERT_DATA - Server CA certificate data (PEM format)
 * - TEMPORAL_TLS_SERVER_NAME - Server name for TLS verification (SNI override)
 * - TEMPORAL_GRPC_META_* - gRPC metadata headers (e.g., TEMPORAL_GRPC_META_X_CUSTOM_HEADER)
 *
 * TLS Configuration Rules:
 * - Cannot specify both *_PATH and *_DATA variants for the same certificate (throws exception)
 * - *_PATH takes precedence over *_DATA if both are set (with strict validation)
 *
 * @link https://github.com/temporalio/proposals/blob/master/all-sdk/external-client-configuration.md#environment-variables
 * @internal
 */
final class ConfigEnv
{
    public function __construct(
        /**
         * Profile constructed from environment variables
         */
        public readonly ConfigProfile $profile,
        public readonly ?string $currentProfile = null,
        public readonly ?string $configFile = null,
    ) {}

    public static function fromEnvProvider(EnvProvider $env): self
    {
        return new self(
            new ConfigProfile(
                address: $env->get('TEMPORAL_ADDRESS'),
                namespace: $env->get('TEMPORAL_NAMESPACE'),
                apiKey: $env->get('TEMPORAL_API_KEY'),
                tlsConfig: self::fetchTlsConfig($env),
                grpcMeta: self::fetchGrpcMeta($env),
            ),
            $env->get('TEMPORAL_PROFILE'),
            $env->get('TEMPORAL_CONFIG_FILE'),
        );
    }

    private static function fetchTlsConfig(EnvProvider $env): ?ConfigTls
    {
        $tls = $env->get('TEMPORAL_TLS');
        $tlsVars = $env->getByPrefix('TEMPORAL_TLS_', stripPrefix: true);

        // If no TLS-related variables are set, return null
        if ($tls === null && $tlsVars === []) {
            return null;
        }

        // Parse TEMPORAL_TLS as boolean
        $disabled = null;
        if ($tls !== null) {
            $tlsEnabled = \filter_var($tls, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
            $disabled = $tlsEnabled === null ? null : !$tlsEnabled;
        }

        // Check for conflicts: *_PATH and *_DATA cannot be used together
        isset($tlsVars['SERVER_CA_CERT_PATH'], $tlsVars['SERVER_CA_CERT_DATA']) and throw new \InvalidArgumentException(
            'Cannot specify both TEMPORAL_TLS_SERVER_CA_CERT_PATH and TEMPORAL_TLS_SERVER_CA_CERT_DATA.',
        );
        isset($tlsVars['CLIENT_KEY_PATH'], $tlsVars['CLIENT_KEY_DATA']) and throw new \InvalidArgumentException(
            'Cannot specify both TEMPORAL_TLS_CLIENT_KEY_PATH and TEMPORAL_TLS_CLIENT_KEY_DATA.',
        );
        isset($tlsVars['CLIENT_CERT_PATH'], $tlsVars['CLIENT_CERT_DATA']) and throw new \InvalidArgumentException(
            'Cannot specify both TEMPORAL_TLS_CLIENT_CERT_PATH and TEMPORAL_TLS_CLIENT_CERT_DATA.',
        );

        // Priority: *_PATH over *_DATA (same as ConfigToml)
        return new ConfigTls(
            disabled: $disabled,
            rootCerts: $tlsVars['SERVER_CA_CERT_PATH'] ?? $tlsVars['SERVER_CA_CERT_DATA'] ?? null,
            privateKey: $tlsVars['CLIENT_KEY_PATH'] ?? $tlsVars['CLIENT_KEY_DATA'] ?? null,
            certChain: $tlsVars['CLIENT_CERT_PATH'] ?? $tlsVars['CLIENT_CERT_DATA'] ?? null,
            serverName: $tlsVars['SERVER_NAME'] ?? null,
        );
    }

    /**
     * Fetch gRPC metadata from environment variables.
     *
     * Reads all environment variables with prefix TEMPORAL_GRPC_META_
     * and converts them to gRPC metadata headers.
     *
     * Header names are transformed:
     * - Converted to lowercase
     * - Underscores (_) are replaced with hyphens (-)
     *
     * Example: TEMPORAL_GRPC_META_X_CUSTOM_HEADER=value
     * Results in: ['x-custom-header' => 'value']
     *
     * @return array<non-empty-string, string>
     */
    private static function fetchGrpcMeta(EnvProvider $env): array
    {
        $meta = $env->getByPrefix('TEMPORAL_GRPC_META_', stripPrefix: true);
        $result = [];

        foreach ($meta as $key => $value) {
            // Transform header name: lowercase and replace _ with -
            $headerName = \str_replace('_', '-', $key);
            $result[$headerName] = $value;
        }

        return $result;
    }
}
