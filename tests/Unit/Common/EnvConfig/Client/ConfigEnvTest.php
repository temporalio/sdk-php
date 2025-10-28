<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common\EnvConfig\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Common\EnvConfig\Client\ConfigEnv;
use Temporal\Common\EnvConfig\Client\ConfigProfile;
use Temporal\Tests\Unit\Common\EnvConfig\Client\Stub\ArrayEnvProvider;

#[CoversClass(ConfigEnv::class)]
final class ConfigEnvTest extends TestCase
{
    private ArrayEnvProvider $envProvider;

    public static function provideAddressFormats(): \Generator
    {
        yield 'localhost with port' => ['localhost:7233'];
        yield 'IP address with port' => ['127.0.0.1:7233'];
        yield 'domain with port' => ['temporal.example.com:7233'];
        yield 'cloud endpoint' => ['my-namespace.tmprl.cloud:7233'];
        yield 'hostname without port' => ['temporal-server'];
    }

    public static function provideBooleanValues(): \Generator
    {
        yield 'true string' => ['true', false];
        yield 'false string' => ['false', true];
        yield '1 numeric' => ['1', false];
        yield '0 numeric' => ['0', true];
        yield 'yes string' => ['yes', false];
        yield 'no string' => ['no', true];
        yield 'on string' => ['on', false];
        yield 'off string' => ['off', true];
    }

    public function testEmptyEnvironment(): void
    {
        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNull($config->currentProfile);
        self::assertNull($config->configFile);
        self::assertInstanceOf(ConfigProfile::class, $config->profile);
        self::assertNull($config->profile->address);
        self::assertNull($config->profile->namespace);
        self::assertNull($config->profile->apiKey);
        self::assertNull($config->profile->tlsConfig);
        self::assertSame([], $config->profile->grpcMeta);
    }

    public function testReadTemporalAddress(): void
    {
        $this->envProvider->set('TEMPORAL_ADDRESS', 'localhost:7233');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame('localhost:7233', $config->profile->address);
    }

    public function testReadTemporalNamespace(): void
    {
        $this->envProvider->set('TEMPORAL_NAMESPACE', 'my-namespace');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame('my-namespace', $config->profile->namespace);
    }

    public function testReadTemporalApiKey(): void
    {
        $this->envProvider->set('TEMPORAL_API_KEY', 'secret-key-123');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame('secret-key-123', $config->profile->apiKey);
    }

    public function testReadTemporalProfile(): void
    {
        $this->envProvider->set('TEMPORAL_PROFILE', 'production');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame('production', $config->currentProfile);
    }

    public function testReadTemporalConfigFile(): void
    {
        $this->envProvider->set('TEMPORAL_CONFIG_FILE', '/path/to/config.toml');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame('/path/to/config.toml', $config->configFile);
    }

    public function testReadMultipleEnvironmentVariables(): void
    {
        $this->envProvider->set('TEMPORAL_ADDRESS', 'cloud.temporal.io:7233');
        $this->envProvider->set('TEMPORAL_NAMESPACE', 'production-ns');
        $this->envProvider->set('TEMPORAL_API_KEY', 'prod-key');
        $this->envProvider->set('TEMPORAL_PROFILE', 'cloud');
        $this->envProvider->set('TEMPORAL_CONFIG_FILE', '/etc/temporal/config.toml');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame('cloud.temporal.io:7233', $config->profile->address);
        self::assertSame('production-ns', $config->profile->namespace);
        self::assertSame('prod-key', $config->profile->apiKey);
        self::assertSame('cloud', $config->currentProfile);
        self::assertSame('/etc/temporal/config.toml', $config->configFile);
    }

    #[DataProvider('provideAddressFormats')]
    public function testAddressFormatVariations(string $address): void
    {
        $this->envProvider->set('TEMPORAL_ADDRESS', $address);

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame($address, $config->profile->address);
    }

    public function testTlsConfigNotSetReturnsNull(): void
    {
        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNull($config->profile->tlsConfig);
    }

    public function testTlsEnabledAsBoolean(): void
    {
        $this->envProvider->set('TEMPORAL_TLS', 'true');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertFalse($config->profile->tlsConfig->disabled);
    }

    public function testTlsDisabledAsBoolean(): void
    {
        $this->envProvider->set('TEMPORAL_TLS', 'false');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertTrue($config->profile->tlsConfig->disabled);
    }

    #[DataProvider('provideBooleanValues')]
    public function testTlsBooleanVariations(string $value, bool $expectedDisabled): void
    {
        $this->envProvider->set('TEMPORAL_TLS', $value);

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame($expectedDisabled, $config->profile->tlsConfig->disabled);
    }

    public function testTlsClientCertPath(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_CERT_PATH', '/path/to/client.crt');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('/path/to/client.crt', $config->profile->tlsConfig->certChain);
    }

    public function testTlsClientKeyPath(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_KEY_PATH', '/path/to/client.key');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('/path/to/client.key', $config->profile->tlsConfig->privateKey);
    }

    public function testTlsServerCaCertPath(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_SERVER_CA_CERT_PATH', '/path/to/ca.crt');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('/path/to/ca.crt', $config->profile->tlsConfig->rootCerts);
    }

    public function testTlsServerName(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_SERVER_NAME', 'temporal.example.com');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('temporal.example.com', $config->profile->tlsConfig->serverName);
    }

    public function testTlsFullConfiguration(): void
    {
        $this->envProvider->set('TEMPORAL_TLS', 'true');
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_CERT_PATH', '/certs/client.crt');
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_KEY_PATH', '/certs/client.key');
        $this->envProvider->set('TEMPORAL_TLS_SERVER_CA_CERT_PATH', '/certs/ca.crt');
        $this->envProvider->set('TEMPORAL_TLS_SERVER_NAME', 'my-temporal.cloud');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertFalse($config->profile->tlsConfig->disabled);
        self::assertSame('/certs/client.crt', $config->profile->tlsConfig->certChain);
        self::assertSame('/certs/client.key', $config->profile->tlsConfig->privateKey);
        self::assertSame('/certs/ca.crt', $config->profile->tlsConfig->rootCerts);
        self::assertSame('my-temporal.cloud', $config->profile->tlsConfig->serverName);
    }

    public function testTlsConfigWithOnlyCertificates(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_CERT_PATH', '/certs/client.crt');
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_KEY_PATH', '/certs/client.key');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertNull($config->profile->tlsConfig->disabled);
        self::assertSame('/certs/client.crt', $config->profile->tlsConfig->certChain);
        self::assertSame('/certs/client.key', $config->profile->tlsConfig->privateKey);
        self::assertNull($config->profile->tlsConfig->rootCerts);
        self::assertNull($config->profile->tlsConfig->serverName);
    }

    public function testTlsClientCertData(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_CERT_DATA', '-----BEGIN CERTIFICATE-----');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('-----BEGIN CERTIFICATE-----', $config->profile->tlsConfig->certChain);
    }

    public function testTlsClientKeyData(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_KEY_DATA', '-----BEGIN PRIVATE KEY-----');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('-----BEGIN PRIVATE KEY-----', $config->profile->tlsConfig->privateKey);
    }

    public function testTlsServerCaCertData(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_SERVER_CA_CERT_DATA', '-----BEGIN CERTIFICATE-----');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('-----BEGIN CERTIFICATE-----', $config->profile->tlsConfig->rootCerts);
    }

    public function testTlsFullConfigurationWithData(): void
    {
        $this->envProvider->set('TEMPORAL_TLS', 'true');
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_CERT_DATA', '-----BEGIN CERTIFICATE-----\ncert-data\n-----END CERTIFICATE-----');
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_KEY_DATA', '-----BEGIN PRIVATE KEY-----\nkey-data\n-----END PRIVATE KEY-----');
        $this->envProvider->set('TEMPORAL_TLS_SERVER_CA_CERT_DATA', '-----BEGIN CA CERT-----\nca-data\n-----END CA CERT-----');
        $this->envProvider->set('TEMPORAL_TLS_SERVER_NAME', 'temporal.cloud');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertFalse($config->profile->tlsConfig->disabled);
        self::assertSame('-----BEGIN CERTIFICATE-----\ncert-data\n-----END CERTIFICATE-----', $config->profile->tlsConfig->certChain);
        self::assertSame('-----BEGIN PRIVATE KEY-----\nkey-data\n-----END PRIVATE KEY-----', $config->profile->tlsConfig->privateKey);
        self::assertSame('-----BEGIN CA CERT-----\nca-data\n-----END CA CERT-----', $config->profile->tlsConfig->rootCerts);
        self::assertSame('temporal.cloud', $config->profile->tlsConfig->serverName);
    }

    public function testTlsClientCertPathAndDataConflict(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_CERT_PATH', '/path/to/cert.crt');
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_CERT_DATA', '-----BEGIN CERTIFICATE-----');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both TEMPORAL_TLS_CLIENT_CERT_PATH and TEMPORAL_TLS_CLIENT_CERT_DATA.');

        ConfigEnv::fromEnvProvider($this->envProvider);
    }

    public function testTlsClientKeyPathAndDataConflict(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_KEY_PATH', '/path/to/key.key');
        $this->envProvider->set('TEMPORAL_TLS_CLIENT_KEY_DATA', '-----BEGIN PRIVATE KEY-----');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both TEMPORAL_TLS_CLIENT_KEY_PATH and TEMPORAL_TLS_CLIENT_KEY_DATA.');

        ConfigEnv::fromEnvProvider($this->envProvider);
    }

    public function testTlsServerCaCertPathAndDataConflict(): void
    {
        $this->envProvider->set('TEMPORAL_TLS_SERVER_CA_CERT_PATH', '/path/to/ca.crt');
        $this->envProvider->set('TEMPORAL_TLS_SERVER_CA_CERT_DATA', '-----BEGIN CERTIFICATE-----');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both TEMPORAL_TLS_SERVER_CA_CERT_PATH and TEMPORAL_TLS_SERVER_CA_CERT_DATA.');

        ConfigEnv::fromEnvProvider($this->envProvider);
    }

    public function testGrpcMetaEmpty(): void
    {
        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame([], $config->profile->grpcMeta);
    }

    public function testGrpcMetaSingleHeader(): void
    {
        $this->envProvider->set('TEMPORAL_GRPC_META_X_CUSTOM_HEADER', 'custom-value');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame(['x-custom-header' => ['custom-value']], $config->profile->grpcMeta);
    }

    public function testGrpcMetaMultipleHeaders(): void
    {
        $this->envProvider->set('TEMPORAL_GRPC_META_X_API_KEY', 'secret-key');
        $this->envProvider->set('TEMPORAL_GRPC_META_X_CLIENT_ID', 'client-123');
        $this->envProvider->set('TEMPORAL_GRPC_META_X_REQUEST_ID', 'req-456');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame([
            'x-api-key' => ['secret-key'],
            'x-client-id' => ['client-123'],
            'x-request-id' => ['req-456'],
        ], $config->profile->grpcMeta);
    }

    public function testGrpcMetaWithSpecialCharacters(): void
    {
        $this->envProvider->set('TEMPORAL_GRPC_META_AUTHORIZATION', 'Bearer token123');
        $this->envProvider->set('TEMPORAL_GRPC_META_CONTENT_TYPE', 'application/json');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame([
            'authorization' => ['Bearer token123'],
            'content-type' => ['application/json'],
        ], $config->profile->grpcMeta);
    }

    public function testGrpcMetaDoesNotIncludeOtherTemporalVars(): void
    {
        $this->envProvider->set('TEMPORAL_ADDRESS', 'localhost:7233');
        $this->envProvider->set('TEMPORAL_NAMESPACE', 'default');
        $this->envProvider->set('TEMPORAL_GRPC_META_X_CUSTOM', 'value');

        $config = ConfigEnv::fromEnvProvider($this->envProvider);

        self::assertSame(['x-custom' => ['value']], $config->profile->grpcMeta);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->envProvider = new ArrayEnvProvider();
    }
}
