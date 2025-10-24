<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

use Internal\Toml\Toml;

/**
 * gRPC TLS configuration.
 *
 * Example TOML:
 *
 * ```toml
 *  [profile.default]
 *  address = "default-address"
 *  namespace = "default-namespace"
 *
 *  [profile.custom]
 *  address = "custom-address"
 *  namespace = "custom-namespace"
 *  api_key = "custom-api-key"
 *  [profile.custom.tls]
 *  server_name = "custom-server-name"
 *  [profile.custom.grpc_meta]
 *  custom-header = "custom-value"
 * ```
 *
 * ```toml
 *  [profile.tls_disabled]
 *  address = "localhost:1234"
 *  [profile.tls_disabled.tls]
 *  disabled = true
 *  server_name = "should-be-ignored"
 *
 *  [profile.tls_with_certs]
 *  address = "localhost:5678"
 *  [profile.tls_with_certs.tls]
 *  server_name = "custom-server"
 *  server_ca_cert_data = "ca-pem-data"
 *  client_cert_data = "client-crt-data"
 *  client_key_data = "client-key-data"
 * ```
 *
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
    private function assertNot(bool $condition, string $message): bool
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
        if ($this->assertNot(\is_array($profile), 'The `profile` section must be an array.')) {
            return [];
        }

        $result = [];
        foreach ($profile as $name => $config) {
            if (
                $this->assertNot(\is_array($config), 'Each profile configuration must be an array.')
                || $this->assertNot(\strlen($name) > 0, 'Profile name must be a non-empty string.')
            ) {
                continue;
            }

            // cert_data and cert_path must not be used together
            $rootCert = $config['tls']['server_ca_cert_path'] ?? $config['tls']['server_ca_cert_data'] ?? null;
            $rootCert === null or $this->assertNot(
                isset($config['tls']['server_ca_cert_path'], $config['tls']['server_ca_cert_data']),
                'Cannot specify both `server_ca_cert_path` and `server_ca_cert_data`.',
            );
            $privateKey = $config['tls']['client_key_path'] ?? $config['tls']['client_key_data'] ?? null;
            $privateKey === null or $this->assertNot(
                isset($config['tls']['client_key_path'], $config['tls']['client_key_data']),
                'Cannot specify both `client_key_path` and `client_key_data`.',
            );
            $certChain = $config['tls']['client_cert_path'] ?? $config['tls']['client_cert_data'] ?? null;
            $certChain === null or $this->assertNot(
                isset($config['tls']['client_cert_path'], $config['tls']['client_cert_data']),
                'Cannot specify both `client_cert_path` and `client_cert_data`.',
            );

            $this->assertNot(
                $privateKey === null xor $certChain === null,
                'Both `client_key_*` and `client_cert_*` must be specified for mTLS.',
            );

            $result[$name] = new ConfigProfile(
                address: $config['address'] ?? null,
                namespace: $config['namespace'] ?? null,
                apiKey: $config['api_key'] ?? null,
                tlsConfig: ($config['tls']['disabled'] ?? false)
                    ? null
                    : new ConfigTls(
                        rootCerts: $rootCert,
                        privateKey: $privateKey,
                        certChain: $certChain,
                        serverName: $config['tls']['server_name'] ?? null,
                    ),
                grpcMeta: $config['grpc_meta'] ?? [],
            );
        }

        return $result;
    }
}
