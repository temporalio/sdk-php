<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common\EnvConfig\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Common\EnvConfig\Client\ConfigCodec;
use Temporal\Common\EnvConfig\Client\ConfigEnv;
use Temporal\Common\EnvConfig\Client\ConfigProfile;
use Temporal\Common\EnvConfig\Exception\CodecNotSupportedException;

#[CoversClass(ConfigEnv::class)]
final class ConfigEnvTest extends TestCase
{
    private array $env;

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
        $config = ConfigEnv::fromEnv($this->env);

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
        $this->env['TEMPORAL_ADDRESS'] = 'localhost:7233';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame('localhost:7233', $config->profile->address);
    }

    public function testReadTemporalNamespace(): void
    {
        $this->env['TEMPORAL_NAMESPACE'] = 'my-namespace';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame('my-namespace', $config->profile->namespace);
    }

    public function testReadTemporalApiKey(): void
    {
        $this->env['TEMPORAL_API_KEY'] = 'secret-key-123';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame('secret-key-123', $config->profile->apiKey);
    }

    public function testReadTemporalProfile(): void
    {
        $this->env['TEMPORAL_PROFILE'] = 'production';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame('production', $config->currentProfile);
    }

    public function testReadTemporalConfigFile(): void
    {
        $this->env['TEMPORAL_CONFIG_FILE'] = '/path/to/config.toml';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame('/path/to/config.toml', $config->configFile);
    }

    public function testReadMultipleEnvironmentVariables(): void
    {
        $this->env['TEMPORAL_ADDRESS'] = 'cloud.temporal.io:7233';
        $this->env['TEMPORAL_NAMESPACE'] = 'production-ns';
        $this->env['TEMPORAL_API_KEY'] = 'prod-key';
        $this->env['TEMPORAL_PROFILE'] = 'cloud';
        $this->env['TEMPORAL_CONFIG_FILE'] = '/etc/temporal/config.toml';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame('cloud.temporal.io:7233', $config->profile->address);
        self::assertSame('production-ns', $config->profile->namespace);
        self::assertSame('prod-key', $config->profile->apiKey);
        self::assertSame('cloud', $config->currentProfile);
        self::assertSame('/etc/temporal/config.toml', $config->configFile);
    }

    #[DataProvider('provideAddressFormats')]
    public function testAddressFormatVariations(string $address): void
    {
        $this->env['TEMPORAL_ADDRESS'] = $address;

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame($address, $config->profile->address);
    }

    public function testTlsConfigNotSetReturnsNull(): void
    {
        $config = ConfigEnv::fromEnv($this->env);

        self::assertNull($config->profile->tlsConfig);
    }

    public function testTlsEnabledAsBoolean(): void
    {
        $this->env['TEMPORAL_TLS'] = 'true';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertFalse($config->profile->tlsConfig->disabled);
    }

    public function testTlsDisabledAsBoolean(): void
    {
        $this->env['TEMPORAL_TLS'] = 'false';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertTrue($config->profile->tlsConfig->disabled);
    }

    #[DataProvider('provideBooleanValues')]
    public function testTlsBooleanVariations(string $value, bool $expectedDisabled): void
    {
        $this->env['TEMPORAL_TLS'] = $value;

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame($expectedDisabled, $config->profile->tlsConfig->disabled);
    }

    public function testTlsClientCertPath(): void
    {
        $this->env['TEMPORAL_TLS_CLIENT_CERT_PATH'] = '/path/to/client.crt';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('/path/to/client.crt', $config->profile->tlsConfig->certChain);
    }

    public function testTlsClientKeyPath(): void
    {
        $this->env['TEMPORAL_TLS_CLIENT_KEY_PATH'] = '/path/to/client.key';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('/path/to/client.key', $config->profile->tlsConfig->privateKey);
    }

    public function testTlsServerCaCertPath(): void
    {
        $this->env['TEMPORAL_TLS_SERVER_CA_CERT_PATH'] = '/path/to/ca.crt';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('/path/to/ca.crt', $config->profile->tlsConfig->rootCerts);
    }

    public function testTlsServerName(): void
    {
        $this->env['TEMPORAL_TLS_SERVER_NAME'] = 'temporal.example.com';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('temporal.example.com', $config->profile->tlsConfig->serverName);
    }

    public function testTlsFullConfiguration(): void
    {
        $this->env['TEMPORAL_TLS'] = 'true';
        $this->env['TEMPORAL_TLS_CLIENT_CERT_PATH'] = '/certs/client.crt';
        $this->env['TEMPORAL_TLS_CLIENT_KEY_PATH'] = '/certs/client.key';
        $this->env['TEMPORAL_TLS_SERVER_CA_CERT_PATH'] = '/certs/ca.crt';
        $this->env['TEMPORAL_TLS_SERVER_NAME'] = 'my-temporal.cloud';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertFalse($config->profile->tlsConfig->disabled);
        self::assertSame('/certs/client.crt', $config->profile->tlsConfig->certChain);
        self::assertSame('/certs/client.key', $config->profile->tlsConfig->privateKey);
        self::assertSame('/certs/ca.crt', $config->profile->tlsConfig->rootCerts);
        self::assertSame('my-temporal.cloud', $config->profile->tlsConfig->serverName);
    }

    public function testTlsConfigWithOnlyCertificates(): void
    {
        $this->env['TEMPORAL_TLS_CLIENT_CERT_PATH'] = '/certs/client.crt';
        $this->env['TEMPORAL_TLS_CLIENT_KEY_PATH'] = '/certs/client.key';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertNull($config->profile->tlsConfig->disabled);
        self::assertSame('/certs/client.crt', $config->profile->tlsConfig->certChain);
        self::assertSame('/certs/client.key', $config->profile->tlsConfig->privateKey);
        self::assertNull($config->profile->tlsConfig->rootCerts);
        self::assertNull($config->profile->tlsConfig->serverName);
    }

    public function testTlsClientCertData(): void
    {
        $this->env['TEMPORAL_TLS_CLIENT_CERT_DATA'] = '-----BEGIN CERTIFICATE-----';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('-----BEGIN CERTIFICATE-----', $config->profile->tlsConfig->certChain);
    }

    public function testTlsClientKeyData(): void
    {
        $this->env['TEMPORAL_TLS_CLIENT_KEY_DATA'] = '-----BEGIN PRIVATE KEY-----';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('-----BEGIN PRIVATE KEY-----', $config->profile->tlsConfig->privateKey);
    }

    public function testTlsServerCaCertData(): void
    {
        $this->env['TEMPORAL_TLS_SERVER_CA_CERT_DATA'] = '-----BEGIN CERTIFICATE-----';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertSame('-----BEGIN CERTIFICATE-----', $config->profile->tlsConfig->rootCerts);
    }

    public function testTlsFullConfigurationWithData(): void
    {
        $this->env['TEMPORAL_TLS'] = 'true';
        $this->env['TEMPORAL_TLS_CLIENT_CERT_DATA'] = '-----BEGIN CERTIFICATE-----\ncert-data\n-----END CERTIFICATE-----';
        $this->env['TEMPORAL_TLS_CLIENT_KEY_DATA'] = '-----BEGIN PRIVATE KEY-----\nkey-data\n-----END PRIVATE KEY-----';
        $this->env['TEMPORAL_TLS_SERVER_CA_CERT_DATA'] = '-----BEGIN CA CERT-----\nca-data\n-----END CA CERT-----';
        $this->env['TEMPORAL_TLS_SERVER_NAME'] = 'temporal.cloud';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertNotNull($config->profile->tlsConfig);
        self::assertFalse($config->profile->tlsConfig->disabled);
        self::assertSame('-----BEGIN CERTIFICATE-----\ncert-data\n-----END CERTIFICATE-----', $config->profile->tlsConfig->certChain);
        self::assertSame('-----BEGIN PRIVATE KEY-----\nkey-data\n-----END PRIVATE KEY-----', $config->profile->tlsConfig->privateKey);
        self::assertSame('-----BEGIN CA CERT-----\nca-data\n-----END CA CERT-----', $config->profile->tlsConfig->rootCerts);
        self::assertSame('temporal.cloud', $config->profile->tlsConfig->serverName);
    }

    public function testTlsClientCertPathAndDataConflict(): void
    {
        $this->env['TEMPORAL_TLS_CLIENT_CERT_PATH'] = '/path/to/cert.crt';
        $this->env['TEMPORAL_TLS_CLIENT_CERT_DATA'] = '-----BEGIN CERTIFICATE-----';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both TEMPORAL_TLS_CLIENT_CERT_PATH and TEMPORAL_TLS_CLIENT_CERT_DATA.');

        ConfigEnv::fromEnv($this->env);
    }

    public function testTlsClientKeyPathAndDataConflict(): void
    {
        $this->env['TEMPORAL_TLS_CLIENT_KEY_PATH'] = '/path/to/key.key';
        $this->env['TEMPORAL_TLS_CLIENT_KEY_DATA'] = '-----BEGIN PRIVATE KEY-----';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both TEMPORAL_TLS_CLIENT_KEY_PATH and TEMPORAL_TLS_CLIENT_KEY_DATA.');

        ConfigEnv::fromEnv($this->env);
    }

    public function testTlsServerCaCertPathAndDataConflict(): void
    {
        $this->env['TEMPORAL_TLS_SERVER_CA_CERT_PATH'] = '/path/to/ca.crt';
        $this->env['TEMPORAL_TLS_SERVER_CA_CERT_DATA'] = '-----BEGIN CERTIFICATE-----';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both TEMPORAL_TLS_SERVER_CA_CERT_PATH and TEMPORAL_TLS_SERVER_CA_CERT_DATA.');

        ConfigEnv::fromEnv($this->env);
    }

    public function testGrpcMetaEmpty(): void
    {
        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame([], $config->profile->grpcMeta);
    }

    public function testGrpcMetaSingleHeader(): void
    {
        $this->env['TEMPORAL_GRPC_META_X_CUSTOM_HEADER'] = 'custom-value';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame(['x-custom-header' => ['custom-value']], $config->profile->grpcMeta);
    }

    public function testGrpcMetaMultipleHeaders(): void
    {
        $this->env['TEMPORAL_GRPC_META_X_API_KEY'] = 'secret-key';
        $this->env['TEMPORAL_GRPC_META_X_CLIENT_ID'] = 'client-123';
        $this->env['TEMPORAL_GRPC_META_X_REQUEST_ID'] = 'req-456';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame([
            'x-api-key' => ['secret-key'],
            'x-client-id' => ['client-123'],
            'x-request-id' => ['req-456'],
        ], $config->profile->grpcMeta);
    }

    public function testGrpcMetaWithSpecialCharacters(): void
    {
        $this->env['TEMPORAL_GRPC_META_AUTHORIZATION'] = 'Bearer token123';
        $this->env['TEMPORAL_GRPC_META_CONTENT_TYPE'] = 'application/json';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame([
            'authorization' => ['Bearer token123'],
            'content-type' => ['application/json'],
        ], $config->profile->grpcMeta);
    }

    public function testGrpcMetaDoesNotIncludeOtherTemporalVars(): void
    {
        $this->env['TEMPORAL_ADDRESS'] = 'localhost:7233';
        $this->env['TEMPORAL_NAMESPACE'] = 'default';
        $this->env['TEMPORAL_GRPC_META_X_CUSTOM'] = 'value';

        $config = ConfigEnv::fromEnv($this->env);

        self::assertSame(['x-custom' => ['value']], $config->profile->grpcMeta);
    }

    public function testThrowsExceptionWhenCodecEndpointIsSet(): void
    {
        // Arrange
        $this->env['TEMPORAL_CODEC_ENDPOINT'] = 'https://codec.example.com';

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        ConfigEnv::fromEnv($this->env);
    }

    public function testThrowsExceptionWhenCodecAuthIsSet(): void
    {
        // Arrange
        $this->env['TEMPORAL_CODEC_AUTH'] = 'Bearer token123';

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        ConfigEnv::fromEnv($this->env);
    }

    public function testThrowsExceptionWhenBothCodecVarsAreSet(): void
    {
        // Arrange
        $this->env['TEMPORAL_CODEC_ENDPOINT'] = 'https://codec.example.com';
        $this->env['TEMPORAL_CODEC_AUTH'] = 'Bearer token123';

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        ConfigEnv::fromEnv($this->env);
    }

    public function testDoesNotThrowExceptionWhenNoCodecVarsAreSet(): void
    {
        // Arrange
        $this->env['TEMPORAL_ADDRESS'] = 'localhost:7233';

        // Act
        $config = ConfigEnv::fromEnv($this->env);

        // Assert
        self::assertNull($config->profile->codecConfig);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = [];
    }
}
