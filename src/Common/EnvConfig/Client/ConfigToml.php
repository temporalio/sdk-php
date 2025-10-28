<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

use Internal\Toml\Toml;

/**
 * TOML configuration parser for Temporal client profiles.
 *
 * Parses TOML configuration files containing multiple named profiles,
 * each with connection settings, TLS configuration, API keys, and gRPC metadata.
 * All configuration keys are optional.
 *
 * TLS Behavior:
 * - By default, TLS is disabled for profiles without `api_key` or explicit `tls` section
 * - TLS is automatically enabled when `api_key` is present or `tls = true`
 * - In strict mode, specifying both `*_path` and `*_data` options throws an exception
 * - In non-strict mode, `*_path` takes precedence over `*_data` if both are specified
 *
 * Example TOML:
 *
 * ```toml
 *  [profile.default]
 *  address = "127.0.0.1:7233"
 *  namespace = "default"
 *
 *  [profile.custom]
 *  address = "custom-address"
 *  namespace = "custom-namespace"
 *  api_key = "custom-api-key"
 *  [profile.custom.tls]
 *  server_name = "custom-server-name"
 *  server_ca_cert_path = "ca-pem-data"
 *  client_cert_data = "client-crt-data"
 *  client_key_data = "client-key-data"
 *  [profile.custom.grpc_meta]
 *  custom-header = "custom-value"
 * ```
 *
 * @link https://github.com/temporalio/proposals/blob/master/all-sdk/external-client-configuration.md#values-and-file-format
 * @internal
 */
final class ConfigToml
{
    /**
     * @var array<non-empty-string, ConfigProfile>
     */
    public readonly array $profiles;

    /**
     * @param string $toml TOML content
     * @param bool $strict Whether to use strict parsing
     */
    public function __construct(
        string $toml,
        private readonly bool $strict = true,
    ) {
        $data = Toml::parseToArray($toml);
        $this->profiles = $this->parseProfiles($data['profile'] ?? []);
    }

    /**
     * Assert a condition and throw an exception if it fails.
     *
     * @param bool $condition The condition to assert.
     * @param non-empty-string $message The exception message if the assertion fails.
     * @return bool Returns true if the condition is false.
     */
    private function notAssert(bool $condition, string $message): bool
    {
        $this->strict and !$condition and throw new \InvalidArgumentException($message);
        return !$condition;
    }

    /**
     * Parse profiles from the given configuration array.
     *
     * @param mixed $profile The profile configuration data.
     * @return array<non-empty-string, ConfigProfile>
     */
    private function parseProfiles(mixed $profile): array
    {
        if ($this->notAssert(\is_array($profile), 'The `profile` section must be an array.')) {
            return [];
        }

        $result = [];
        foreach ($profile as $name => $config) {
            if (
                $this->notAssert(\is_array($config), 'Each profile configuration must be an array.')
                || $this->notAssert(\strlen($name) > 0, 'Profile name must be a non-empty string.')
            ) {
                continue;
            }

            $apiKey = $config['api_key'] ?? null;
            $tls = $config['tls'] ?? null;
            $tlsConfig = match (true) {
                \is_array($tls) => $this->parseTls($tls),
                $apiKey !== null || $tls === true => new ConfigTls(),
                default => new ConfigTls(disabled: true),
            };

            $result[$name] = new ConfigProfile(
                address: $config['address'] ?? null,
                namespace: $config['namespace'] ?? null,
                apiKey: $apiKey,
                tlsConfig: $tlsConfig,
                grpcMeta: $config['grpc_meta'] ?? [],
            );
        }

        return $result;
    }

    private function parseTls(array $tls): ?ConfigTls
    {
        // cert_data and cert_path must not be used together
        $rootCert = $tls['server_ca_cert_path'] ?? $tls['server_ca_cert_data'] ?? null;
        $privateKey = $tls['client_key_path'] ?? $tls['client_key_data'] ?? null;
        $certChain = $tls['client_cert_path'] ?? $tls['client_cert_data'] ?? null;

        $rootCert === null or $this->notAssert(
            isset($tls['server_ca_cert_path']) xor isset($tls['server_ca_cert_data']),
            'Cannot specify both `server_ca_cert_path` and `server_ca_cert_data`.',
        );
        $privateKey === null or $this->notAssert(
            isset($tls['client_key_path']) xor isset($tls['client_key_data']),
            'Cannot specify both `client_key_path` and `client_key_data`.',
        );
        $certChain === null or $this->notAssert(
            isset($tls['client_cert_path']) xor isset($tls['client_cert_data']),
            'Cannot specify both `client_cert_path` and `client_cert_data`.',
        );
        $this->notAssert(
            ($privateKey === null) === ($certChain === null),
            'Both `client_key_*` and `client_cert_*` must be specified for mTLS.',
        );

        return new ConfigTls(
            disabled: $tls['disabled'] ?? false,
            rootCerts: $rootCert,
            privateKey: $privateKey,
            certChain: $certChain,
            serverName: $tls['server_name'] ?? null,
        );
    }
}
