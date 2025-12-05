<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

use Internal\Toml\Toml;
use Temporal\Common\EnvConfig\Exception\TomlParserNotFoundException;

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
    public function __construct(
        /**
         * @var array<non-empty-string, ConfigProfile>
         */
        public readonly array $profiles,
    ) {}

    /**
     * @param string $toml TOML content
     */
    public static function fromString(string $toml): self
    {
        \class_exists(Toml::class) or throw new TomlParserNotFoundException();
        $data = Toml::parseToArray($toml);
        return new self(self::parseProfiles($data['profile'] ?? []));
    }

    /**
     * Convert the configuration back to TOML string.
     *
     * @return string TOML representation of the configuration
     */
    public function toToml(): string
    {
        return (string) Toml::encode([
            'profile' => \array_map(self::encodeProfile(...), $this->profiles),
        ]);
    }

    /**
     * Assert a condition and throw an exception if it fails.
     *
     * @param bool $condition The condition to assert.
     * @param non-empty-string $message The exception message if the assertion fails.
     * @throws \InvalidArgumentException If the assertion fails.
     */
    private static function assert(bool $condition, string $message): void
    {
        $condition or throw new \InvalidArgumentException($message);
    }

    /**
     * Parse profiles from the given configuration array.
     *
     * @param mixed $profile The profile configuration data.
     * @return array<non-empty-string, ConfigProfile>
     * @throws \InvalidArgumentException If the configuration is invalid.
     */
    private static function parseProfiles(mixed $profile): array
    {
        self::assert(\is_array($profile), 'The `profile` section must be an array.');

        $result = [];
        foreach ($profile as $name => $config) {
            self::assert(\is_array($config), 'Each profile configuration must be an array.');
            self::assert(\strlen($name) > 0, 'Profile name must be a non-empty string.');

            $apiKey = $config['api_key'] ?? null;
            $tls = $config['tls'] ?? null;
            $tlsConfig = match (true) {
                \is_array($tls) => self::parseTls($tls),
                $apiKey !== null || $tls === true => new ConfigTls(),
                default => new ConfigTls(disabled: true),
            };

            /** @var non-empty-string $name */
            $result[$name] = new ConfigProfile(
                address: $config['address'] ?? null,
                namespace: $config['namespace'] ?? null,
                apiKey: $apiKey,
                tlsConfig: $tlsConfig,
                grpcMeta: $config['grpc_meta'] ?? [],
                codecConfig: isset($config['codec']) && \is_array($config['codec']) ? self::parseCodec($config['codec']) : null,
            );
        }

        return $result;
    }

    private static function parseTls(array $tls): ?ConfigTls
    {
        // cert_data and cert_path must not be used together
        $rootCert = $tls['server_ca_cert_path'] ?? $tls['server_ca_cert_data'] ?? null;
        $privateKey = $tls['client_key_path'] ?? $tls['client_key_data'] ?? null;
        $certChain = $tls['client_cert_path'] ?? $tls['client_cert_data'] ?? null;

        $rootCert === null or self::assert(
            isset($tls['server_ca_cert_path']) xor isset($tls['server_ca_cert_data']),
            'Cannot specify both `server_ca_cert_path` and `server_ca_cert_data`.',
        );
        $privateKey === null or self::assert(
            isset($tls['client_key_path']) xor isset($tls['client_key_data']),
            'Cannot specify both `client_key_path` and `client_key_data`.',
        );
        $certChain === null or self::assert(
            isset($tls['client_cert_path']) xor isset($tls['client_cert_data']),
            'Cannot specify both `client_cert_path` and `client_cert_data`.',
        );
        self::assert(
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

    /**
     * Parse codec configuration from TOML array.
     *
     * @param array $codec Codec configuration array
     * @return ConfigCodec|null Parsed codec configuration or null if empty
     */
    private static function parseCodec(array $codec): ?ConfigCodec
    {
        $endpoint = $codec['endpoint'] ?? null;
        $auth = $codec['auth'] ?? null;

        // Return null if both fields are empty
        if ($endpoint === null && $auth === null) {
            return null;
        }

        return new ConfigCodec(
            endpoint: $endpoint,
            auth: $auth,
        );
    }

    private static function encodeProfile(ConfigProfile $profile): array
    {
        $result = [];
        $profile->address === null or $result['address'] = $profile->address;
        $profile->namespace === null or $result['namespace'] = $profile->namespace;
        $profile->apiKey === null or $result['api_key'] = (string) $profile->apiKey;
        $profile->tlsConfig === null or $result['tls'] = self::encodeTls($profile->tlsConfig);
        $profile->grpcMeta === [] or $result['grpc_meta'] = $profile->grpcMeta;
        $profile->codecConfig === null or $result['codec'] = self::encodeCodec($profile->codecConfig);
        return $result;
    }

    private static function encodeTls(ConfigTls $config): array
    {
        $result = [];
        $config->disabled === null or $result['disabled'] = $config->disabled;
        $config->rootCerts === null or self::isCertFile($config->rootCerts)
            ? $result['server_ca_cert_path'] = $config->rootCerts
            : $result['server_ca_cert_data'] = $config->rootCerts;
        $config->privateKey === null or self::isCertFile($config->privateKey)
            ? $result['client_key_path'] = $config->privateKey
            : $result['client_key_data'] = $config->privateKey;
        $config->certChain === null or self::isCertFile($config->certChain)
            ? $result['client_cert_path'] = $config->certChain
            : $result['client_cert_data'] = $config->certChain;
        $config->serverName === null or $result['server_name'] = $config->serverName;
        return $result;
    }

    private static function isCertFile(string $certOrPath): bool
    {
        return \str_starts_with($certOrPath, '/')
            || \str_starts_with($certOrPath, './')
            || \str_starts_with($certOrPath, '../');
    }

    private static function encodeCodec(ConfigCodec $config): array
    {
        $result = [];
        $config->endpoint === null or $result['endpoint'] = $config->endpoint;
        $config->auth === null or $result['auth'] = $config->auth;
        return $result;
    }
}
