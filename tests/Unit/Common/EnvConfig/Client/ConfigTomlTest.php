<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common\EnvConfig\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Common\EnvConfig\Client\ConfigProfile;
use Temporal\Common\EnvConfig\Client\ConfigTls;
use Temporal\Common\EnvConfig\Client\ConfigToml;
use Temporal\Common\EnvConfig\Exception\CodecNotSupportedException;

#[CoversClass(ConfigToml::class)]
final class ConfigTomlTest extends TestCase
{
    public static function provideInvalidProfileStructures(): \Generator
    {
        yield 'profile section is not an array' => [
            <<<'TOML'
                profile = "invalid"
                TOML,
            'The `profile` section must be an array.',
        ];

        yield 'profile configuration is not an array' => [
            <<<'TOML'
                [profile]
                default = "invalid"
                TOML,
            'Each profile configuration must be an array.',
        ];
    }

    public static function provideComplexConfigurations(): \Generator
    {
        yield 'mixed profiles with various configurations' => [
            <<<'TOML'
                [profile.minimal]
                address = "minimal.example.com:7233"

                [profile.with_namespace]
                address = "ns.example.com:7233"
                namespace = "my-namespace"

                [profile.with_api_key]
                address = "api.example.com:7233"
                namespace = "api-namespace"
                api_key = "my-secret-key"

                [profile.with_all]
                address = "all.example.com:7233"
                namespace = "all-namespace"
                api_key = "all-secret-key"
                [profile.with_all.tls]
                server_name = "all-server"
                server_ca_cert_data = "all-ca-data"
                client_cert_data = "all-cert-data"
                client_key_data = "all-key-data"
                [profile.with_all.grpc_meta]
                header1 = "value1"
                header2 = "value2"
                TOML,
            4,
            [
                'minimal' => [
                    'address' => 'minimal.example.com:7233',
                    'namespace' => null,
                    'apiKey' => null,
                    'tlsConfig' => [
                        'disabled' => true,
                        'serverName' => null,
                        'rootCerts' => null,
                        'certChain' => null,
                        'privateKey' => null,
                    ],
                    'grpcMeta' => [],
                ],
                'with_namespace' => [
                    'address' => 'ns.example.com:7233',
                    'namespace' => 'my-namespace',
                    'apiKey' => null,
                    'tlsConfig' => [
                        'disabled' => true,
                        'serverName' => null,
                        'rootCerts' => null,
                        'certChain' => null,
                        'privateKey' => null,
                    ],
                ],
                'with_api_key' => [
                    'address' => 'api.example.com:7233',
                    'namespace' => 'api-namespace',
                    'apiKey' => 'my-secret-key',
                    'tlsConfig' => [
                        'disabled' => false,
                        'serverName' => null,
                        'rootCerts' => null,
                        'certChain' => null,
                        'privateKey' => null,
                    ],
                ],
                'with_all' => [
                    'address' => 'all.example.com:7233',
                    'namespace' => 'all-namespace',
                    'apiKey' => 'all-secret-key',
                    'grpcMeta' => ['header1' => ['value1'], 'header2' => ['value2']],
                    'tlsConfig' => [
                        'disabled' => false,
                        'serverName' => 'all-server',
                        'rootCerts' => 'all-ca-data',
                        'certChain' => 'all-cert-data',
                        'privateKey' => 'all-key-data',
                    ],
                ],
            ],
        ];

        yield 'profiles with different TLS configurations' => [
            <<<'TOML'
                [profile.tls_paths]
                address = "paths.example.com:7233"
                [profile.tls_paths.tls]
                server_ca_cert_path = "path/to/ca.pem"
                client_cert_path = "path/to/cert.pem"
                client_key_path = "path/to/key.pem"

                [profile.tls_data]
                address = "data.example.com:7233"
                [profile.tls_data.tls]
                server_ca_cert_data = "ca-data"
                client_cert_data = "cert-data"
                client_key_data = "key-data"

                [profile.tls_mixed]
                address = "mixed.example.com:7233"
                [profile.tls_mixed.tls]
                server_ca_cert_path = "path/to/ca.pem"
                client_cert_data = "cert-data"
                client_key_data = "key-data"
                TOML,
            3,
            [
                'tls_paths' => [
                    'tlsConfig' => [
                        'disabled' => false,
                        'rootCerts' => 'path/to/ca.pem',
                        'certChain' => 'path/to/cert.pem',
                        'privateKey' => 'path/to/key.pem',
                    ],
                ],
                'tls_data' => [
                    'tlsConfig' => [
                        'disabled' => false,
                        'rootCerts' => 'ca-data',
                        'certChain' => 'cert-data',
                        'privateKey' => 'key-data',
                    ],
                ],
                'tls_mixed' => [
                    'tlsConfig' => [
                        'disabled' => false,
                        'rootCerts' => 'path/to/ca.pem',
                        'certChain' => 'cert-data',
                        'privateKey' => 'key-data',
                    ],
                ],
            ],
        ];
    }

    public function testConstructorParsesDefaultProfileWithTlsPaths(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.default]
            address = "my-ns.a1b2c.tmprl.cloud:7233"
            namespace = "my-ns.a1b2c"
            tls.client_cert_path = "path/to/my/client.pem"
            tls.client_key_path = "path/to/my/client.pem"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        self::assertArrayHasKey('default', $config->profiles);

        $defaultProfile = $config->profiles['default'];
        self::assertInstanceOf(ConfigProfile::class, $defaultProfile);
        self::assertSame('my-ns.a1b2c.tmprl.cloud:7233', $defaultProfile->address);
        self::assertSame('my-ns.a1b2c', $defaultProfile->namespace);
        self::assertNull($defaultProfile->apiKey);
        self::assertSame([], $defaultProfile->grpcMeta);

        self::assertInstanceOf(ConfigTls::class, $defaultProfile->tlsConfig);
        self::assertSame('path/to/my/client.pem', $defaultProfile->tlsConfig->privateKey);
        self::assertSame('path/to/my/client.pem', $defaultProfile->tlsConfig->certChain);
        self::assertNull($defaultProfile->tlsConfig->rootCerts);
        self::assertNull($defaultProfile->tlsConfig->serverName);
    }

    public function testConstructorParsesMultipleProfiles(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.dev]
            address = "my-dev-ns.a1b2c.tmprl.cloud:7233"
            namespace = "my-dev-ns.a1b2c"
            tls.client_cert_path = "path/to/my/dev-cert.pem"
            tls.client_key_path = "path/to/my/dev-cert.pem"

            [profile.prod]
            address = "my-prod-ns.a1b2c.tmprl.cloud:7233"
            namespace = "my-prod-ns.a1b2c"
            tls.client_cert_path = "path/to/my/prod-cert.pem"
            tls.client_key_path = "path/to/my/prod-cert.pem"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(2, $config->profiles);
        self::assertArrayHasKey('dev', $config->profiles);
        self::assertArrayHasKey('prod', $config->profiles);

        $devProfile = $config->profiles['dev'];
        self::assertSame('my-dev-ns.a1b2c.tmprl.cloud:7233', $devProfile->address);
        self::assertSame('my-dev-ns.a1b2c', $devProfile->namespace);

        $prodProfile = $config->profiles['prod'];
        self::assertSame('my-prod-ns.a1b2c.tmprl.cloud:7233', $prodProfile->address);
        self::assertSame('my-prod-ns.a1b2c', $prodProfile->namespace);
    }

    public function testConstructorParsesProfileWithApiKeyAndGrpcMeta(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.default]
            address = "default-address"
            namespace = "default-namespace"
            [profile.default.tls]
            disabled = true

            [profile.custom]
            address = "custom-address"
            namespace = "custom-namespace"
            api_key = "custom-api-key"
            [profile.custom.tls]
            server_name = "custom-server-name"
            [profile.custom.grpc_meta]
            custom-header = "custom-value"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(2, $config->profiles);

        $defaultProfile = $config->profiles['default'];
        self::assertSame('default-address', $defaultProfile->address);
        self::assertSame('default-namespace', $defaultProfile->namespace);
        self::assertNull($defaultProfile->apiKey);
        self::assertInstanceOf(ConfigTls::class, $defaultProfile->tlsConfig);
        self::assertTrue($defaultProfile->tlsConfig->disabled);
        self::assertSame([], $defaultProfile->grpcMeta);

        $customProfile = $config->profiles['custom'];
        self::assertSame('custom-address', $customProfile->address);
        self::assertSame('custom-namespace', $customProfile->namespace);
        self::assertSame('custom-api-key', $customProfile->apiKey);
        self::assertSame(['custom-header' => ['custom-value']], $customProfile->grpcMeta);

        self::assertInstanceOf(ConfigTls::class, $customProfile->tlsConfig);
        self::assertSame('custom-server-name', $customProfile->tlsConfig->serverName);
        self::assertNull($customProfile->tlsConfig->rootCerts);
        self::assertNull($customProfile->tlsConfig->privateKey);
        self::assertNull($customProfile->tlsConfig->certChain);
    }

    public function testConstructorParsesProfileWithTlsDisabled(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.tls_disabled]
            address = "localhost:1234"
            [profile.tls_disabled.tls]
            disabled = true
            server_name = "should-be-ignored"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        self::assertArrayHasKey('tls_disabled', $config->profiles);

        $profile = $config->profiles['tls_disabled'];
        self::assertSame('localhost:1234', $profile->address);

        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertTrue($profile->tlsConfig->disabled, 'TLS should be disabled');
        self::assertSame('should-be-ignored', $profile->tlsConfig->serverName);
    }

    public function testConstructorParsesProfileWithTlsCertData(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.tls_with_certs]
            address = "localhost:5678"
            [profile.tls_with_certs.tls]
            server_name = "custom-server"
            server_ca_cert_data = "ca-pem-data"
            client_cert_data = "client-crt-data"
            client_key_data = "client-key-data"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        self::assertArrayHasKey('tls_with_certs', $config->profiles);

        $profile = $config->profiles['tls_with_certs'];
        self::assertSame('localhost:5678', $profile->address);

        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertSame('custom-server', $profile->tlsConfig->serverName);
        self::assertSame('ca-pem-data', $profile->tlsConfig->rootCerts);
        self::assertSame('client-crt-data', $profile->tlsConfig->certChain);
        self::assertSame('client-key-data', $profile->tlsConfig->privateKey);
    }

    public function testConstructorHandlesEmptyToml(): void
    {
        // Arrange
        $toml = '';

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertEmpty($config->profiles);
    }

    public function testConstructorHandlesTomlWithoutProfiles(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [some_other_section]
            key = "value"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertEmpty($config->profiles);
    }

    public function testConstructorInStrictModeThrowsExceptionForDuplicateCertPath(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.invalid]
            address = "localhost:1234"
            [profile.invalid.tls]
            server_ca_cert_path = "path/to/ca.pem"
            server_ca_cert_data = "ca-pem-data"
            TOML;

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both `server_ca_cert_path` and `server_ca_cert_data`.');

        // Act
        new ConfigToml($toml);
    }

    public function testConstructorInStrictModeThrowsExceptionForDuplicateClientKeyPath(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.invalid]
            address = "localhost:1234"
            [profile.invalid.tls]
            client_key_path = "path/to/key.pem"
            client_key_data = "key-data"
            TOML;

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both `client_key_path` and `client_key_data`.');

        // Act
        new ConfigToml($toml);
    }

    public function testConstructorInStrictModeThrowsExceptionForDuplicateClientCertPath(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.invalid]
            address = "localhost:1234"
            [profile.invalid.tls]
            client_cert_path = "path/to/cert.pem"
            client_cert_data = "cert-data"
            TOML;

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both `client_cert_path` and `client_cert_data`.');

        // Act
        new ConfigToml($toml);
    }

    public function testConstructorInStrictModeThrowsExceptionForMissingClientKey(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.invalid]
            address = "localhost:1234"
            [profile.invalid.tls]
            client_cert_data = "cert-data"
            TOML;

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Both `client_key_*` and `client_cert_*` must be specified for mTLS.');

        // Act
        new ConfigToml($toml);
    }

    public function testConstructorInStrictModeThrowsExceptionForMissingClientCert(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.invalid]
            address = "localhost:1234"
            [profile.invalid.tls]
            client_key_data = "key-data"
            TOML;

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Both `client_key_*` and `client_cert_*` must be specified for mTLS.');

        // Act
        new ConfigToml($toml);
    }

    #[DataProvider('provideInvalidProfileStructures')]
    public function testConstructorInStrictModeThrowsExceptionForInvalidStructure(
        string $toml,
        string $expectedMessage,
    ): void {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        // Act
        new ConfigToml($toml);
    }

    #[DataProvider('provideComplexConfigurations')]
    public function testConstructorHandlesComplexConfigurations(
        string $toml,
        int $expectedProfileCount,
        array $profileChecks,
    ): void {
        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount($expectedProfileCount, $config->profiles);

        foreach ($profileChecks as $profileName => $checks) {
            self::assertArrayHasKey($profileName, $config->profiles);
            $profile = $config->profiles[$profileName];

            foreach ($checks as $property => $expectedValue) {
                if ($property === 'tlsConfig') {
                    if ($expectedValue === null) {
                        self::assertNull($profile->tlsConfig);
                    } else {
                        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
                        foreach ($expectedValue as $tlsProperty => $tlsValue) {
                            self::assertSame($tlsValue, $profile->tlsConfig->$tlsProperty);
                        }
                    }
                } else {
                    self::assertSame($expectedValue, $profile->$property);
                }
            }
        }
    }

    public function testConstructorParsesProfilesWithOnlyTlsServerName(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.server_name_only]
            address = "example.com:7233"
            [profile.server_name_only.tls]
            server_name = "custom-server-name"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        $profile = $config->profiles['server_name_only'];

        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertSame('custom-server-name', $profile->tlsConfig->serverName);
        self::assertNull($profile->tlsConfig->rootCerts);
        self::assertNull($profile->tlsConfig->privateKey);
        self::assertNull($profile->tlsConfig->certChain);
    }

    public function testConstructorHandlesEmptyGrpcMeta(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.empty_meta]
            address = "example.com:7233"
            [profile.empty_meta.grpc_meta]
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        $profile = $config->profiles['empty_meta'];
        self::assertSame([], $profile->grpcMeta);
    }

    public function testConstructorParsesProfileWithTlsEnabledByApiKey(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.with_api_key]
            address = "api.example.com:7233"
            namespace = "api-namespace"
            api_key = "my-api-key"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        $profile = $config->profiles['with_api_key'];

        self::assertSame('api.example.com:7233', $profile->address);
        self::assertSame('api-namespace', $profile->namespace);
        self::assertSame('my-api-key', $profile->apiKey);

        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertFalse($profile->tlsConfig->disabled);
        self::assertNull($profile->tlsConfig->serverName);
        self::assertNull($profile->tlsConfig->rootCerts);
        self::assertNull($profile->tlsConfig->privateKey);
        self::assertNull($profile->tlsConfig->certChain);
    }

    public function testConstructorParsesProfileWithTlsDisabledByDefault(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.minimal]
            address = "minimal.example.com:7233"
            namespace = "minimal-namespace"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        $profile = $config->profiles['minimal'];

        self::assertSame('minimal.example.com:7233', $profile->address);
        self::assertSame('minimal-namespace', $profile->namespace);
        self::assertNull($profile->apiKey);

        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertTrue($profile->tlsConfig->disabled);
    }

    public function testConstructorParsesProfileWithTlsEnabledExplicitly(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.explicit_tls]
            address = "tls.example.com:7233"
            tls = true
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        $profile = $config->profiles['explicit_tls'];

        self::assertSame('tls.example.com:7233', $profile->address);
        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertFalse($profile->tlsConfig->disabled);
    }

    public function testConstructorParsesProfileWithDisabledFalse(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.tls_enabled]
            address = "enabled.example.com:7233"
            [profile.tls_enabled.tls]
            disabled = false
            server_name = "enabled-server"
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        $profile = $config->profiles['tls_enabled'];

        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertFalse($profile->tlsConfig->disabled);
        self::assertSame('enabled-server', $profile->tlsConfig->serverName);
    }

    public function testConstructorThrowsExceptionWhenCodecEndpointIsConfigured(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.with_codec]
            address = "codec.example.com:7233"
            [profile.with_codec.codec]
            endpoint = "https://codec.example.com"
            TOML;

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        new ConfigToml($toml);
    }

    public function testConstructorThrowsExceptionWhenCodecAuthIsConfigured(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.with_codec_auth]
            address = "codec.example.com:7233"
            [profile.with_codec_auth.codec]
            auth = "Bearer token123"
            TOML;

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        new ConfigToml($toml);
    }

    public function testConstructorThrowsExceptionWhenBothCodecFieldsAreConfigured(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.with_full_codec]
            address = "codec.example.com:7233"
            [profile.with_full_codec.codec]
            endpoint = "https://codec.example.com"
            auth = "Bearer token123"
            TOML;

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        new ConfigToml($toml);
    }

    public function testConstructorDoesNotThrowExceptionForEmptyCodecSection(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.empty_codec]
            address = "example.com:7233"
            [profile.empty_codec.codec]
            TOML;

        // Act
        $config = new ConfigToml($toml);

        // Assert
        self::assertCount(1, $config->profiles);
        $profile = $config->profiles['empty_codec'];
        self::assertNull($profile->codecConfig);
    }
}
