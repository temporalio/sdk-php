<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common\EnvConfig\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Common\EnvConfig\Client\ConfigCodec;
use Temporal\Common\EnvConfig\Client\ConfigProfile;
use Temporal\Common\EnvConfig\Client\ConfigTls;
use Temporal\Common\EnvConfig\Exception\CodecNotSupportedException;

#[CoversClass(ConfigProfile::class)]
final class ConfigProfileTest extends TestCase
{
    public static function provideStringableApiKeys(): \Generator
    {
        yield 'string api key' => ['api-key-string', 'api-key-string'];
        yield 'stringable object' => [
            new class {
                public function __toString(): string
                {
                    return 'stringable-api-key';
                }
            },
            'stringable-api-key',
        ];
    }

    public static function provideGrpcMetaVariations(): \Generator
    {
        yield 'single value string' => [
            ['X-Custom-Header' => 'value'],
            ['x-custom-header' => ['value']],
        ];
        yield 'single value array' => [
            ['X-Custom-Header' => ['value']],
            ['x-custom-header' => ['value']],
        ];
        yield 'multiple values' => [
            ['X-Custom-Header' => ['value1', 'value2']],
            ['x-custom-header' => ['value1', 'value2']],
        ];
        yield 'mixed case keys normalized' => [
            ['Authorization' => 'Bearer token', 'X-API-Key' => 'key123'],
            ['authorization' => ['Bearer token'], 'x-api-key' => ['key123']],
        ];
    }

    public function testConstructorInitializesBasicProperties(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: 'test-key',
        );

        // Assert
        self::assertSame('localhost:7233', $profile->address);
        self::assertSame('default', $profile->namespace);
        self::assertSame('test-key', $profile->apiKey);
    }

    public function testConstructorWithNullValues(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
        );

        // Assert
        self::assertNull($profile->address);
        self::assertNull($profile->namespace);
        self::assertNull($profile->apiKey);
        self::assertNull($profile->tlsConfig);
        self::assertSame([], $profile->grpcMeta);
        self::assertNull($profile->codecConfig);
    }

    public function testConstructorNormalizesEmptyStringToNull(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: '',
            namespace: '',
            apiKey: '',
        );

        // Assert
        self::assertNull($profile->address);
        self::assertNull($profile->namespace);
        self::assertNull($profile->apiKey);
    }

    public function testConstructorCreatesDefaultTlsWhenApiKeyIsProvided(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'cloud.temporal.io:7233',
            namespace: 'my-namespace',
            apiKey: 'api-key-123',
        );

        // Assert
        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertFalse($profile->tlsConfig->disabled);
        self::assertNull($profile->tlsConfig->rootCerts);
        self::assertNull($profile->tlsConfig->privateKey);
        self::assertNull($profile->tlsConfig->certChain);
        self::assertNull($profile->tlsConfig->serverName);
    }

    public function testConstructorDoesNotCreateDefaultTlsWhenNoApiKey(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
        );

        // Assert
        self::assertNull($profile->tlsConfig);
    }

    public function testConstructorUsesProvidedTlsConfigOverDefault(): void
    {
        // Arrange
        $tlsConfig = new ConfigTls(
            disabled: true,
            rootCerts: 'ca.pem',
            privateKey: 'key.pem',
            certChain: 'cert.pem',
            serverName: 'custom-server',
        );

        // Act
        $profile = new ConfigProfile(
            address: 'cloud.temporal.io:7233',
            namespace: 'my-namespace',
            apiKey: 'api-key-123',
            tlsConfig: $tlsConfig,
        );

        // Assert
        self::assertSame($tlsConfig, $profile->tlsConfig);
        self::assertTrue($profile->tlsConfig->disabled);
        self::assertSame('ca.pem', $profile->tlsConfig->rootCerts);
    }

    public function testConstructorNormalizesGrpcMetaKeysToLowercase(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            grpcMeta: [
                'X-Custom-Header' => 'value1',
                'Authorization' => 'Bearer token',
            ],
        );

        // Assert
        self::assertSame([
            'x-custom-header' => ['value1'],
            'authorization' => ['Bearer token'],
        ], $profile->grpcMeta);
    }

    public function testConstructorConvertsGrpcMetaStringsToArrays(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            grpcMeta: [
                'header1' => 'single-value',
                'header2' => ['array-value'],
            ],
        );

        // Assert
        self::assertSame([
            'header1' => ['single-value'],
            'header2' => ['array-value'],
        ], $profile->grpcMeta);
    }

    #[DataProvider('provideStringableApiKeys')]
    public function testConstructorAcceptsStringableApiKey(
        string|\Stringable $apiKey,
        string $expectedString,
    ): void {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: $apiKey,
        );

        // Assert
        self::assertSame($expectedString, (string) $profile->apiKey);
    }

    #[DataProvider('provideGrpcMetaVariations')]
    public function testConstructorHandlesGrpcMetaVariations(
        array $input,
        array $expected,
    ): void {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            grpcMeta: $input,
        );

        // Assert
        self::assertSame($expected, $profile->grpcMeta);
    }

    public function testConstructorThrowsExceptionWhenCodecEndpointIsSet(): void
    {
        // Arrange
        $codecConfig = new ConfigCodec(
            endpoint: 'https://codec.example.com',
            auth: null,
        );

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            codecConfig: $codecConfig,
        );
    }

    public function testConstructorThrowsExceptionWhenCodecAuthIsSet(): void
    {
        // Arrange
        $codecConfig = new ConfigCodec(
            endpoint: null,
            auth: 'Bearer token123',
        );

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            codecConfig: $codecConfig,
        );
    }

    public function testConstructorThrowsExceptionWhenBothCodecFieldsAreSet(): void
    {
        // Arrange
        $codecConfig = new ConfigCodec(
            endpoint: 'https://codec.example.com',
            auth: 'Bearer token123',
        );

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            codecConfig: $codecConfig,
        );
    }

    public function testConstructorDoesNotThrowExceptionForEmptyCodec(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            codecConfig: new ConfigCodec(),
        );

        // Assert
        self::assertInstanceOf(ConfigCodec::class, $profile->codecConfig);
    }

    public function testMergeWithOverridesAddress(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'old-address:7233',
            namespace: 'old-namespace',
            apiKey: 'old-key',
        );
        $override = new ConfigProfile(
            address: 'new-address:7233',
            namespace: null,
            apiKey: null,
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame('new-address:7233', $merged->address);
        self::assertSame('old-namespace', $merged->namespace);
        self::assertSame('old-key', $merged->apiKey);
    }

    public function testMergeWithOverridesNamespace(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'old-namespace',
            apiKey: null,
        );
        $override = new ConfigProfile(
            address: null,
            namespace: 'new-namespace',
            apiKey: null,
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame('localhost:7233', $merged->address);
        self::assertSame('new-namespace', $merged->namespace);
    }

    public function testMergeWithOverridesApiKey(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: 'old-key',
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: 'new-key',
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame('new-key', $merged->apiKey);
    }

    public function testMergeWithMergesTlsConfigs(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            tlsConfig: new ConfigTls(
                disabled: false,
                rootCerts: 'old-ca.pem',
                privateKey: 'old-key.pem',
            ),
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            tlsConfig: new ConfigTls(
                disabled: true,
                rootCerts: 'new-ca.pem',
            ),
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertInstanceOf(ConfigTls::class, $merged->tlsConfig);
        self::assertTrue($merged->tlsConfig->disabled);
        self::assertSame('new-ca.pem', $merged->tlsConfig->rootCerts);
        self::assertSame('old-key.pem', $merged->tlsConfig->privateKey);
    }

    public function testMergeWithMergesGrpcMeta(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            grpcMeta: [
                'header1' => ['value1'],
                'header2' => ['value2'],
            ],
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            grpcMeta: [
                'header2' => ['new-value2'],
                'header3' => ['value3'],
            ],
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame([
            'header1' => ['value1'],
            'header2' => ['new-value2'],
            'header3' => ['value3'],
        ], $merged->grpcMeta);
    }

    public function testMergeWithNullOverrideKeepsBaseValues(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: 'api-key',
            tlsConfig: new ConfigTls(disabled: false),
            grpcMeta: ['header1' => ['value1']],
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame('localhost:7233', $merged->address);
        self::assertSame('default', $merged->namespace);
        self::assertSame('api-key', $merged->apiKey);
        self::assertInstanceOf(ConfigTls::class, $merged->tlsConfig);
        self::assertSame(['header1' => ['value1']], $merged->grpcMeta);
    }

    public function testToClientOptionsWithNamespace(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'my-namespace',
            apiKey: null,
        );

        // Act
        $options = $profile->toClientOptions();

        // Assert
        self::assertInstanceOf(ClientOptions::class, $options);
        self::assertSame('my-namespace', $options->namespace);
    }

    public function testToClientOptionsWithoutNamespace(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: null,
            apiKey: null,
        );

        // Act
        $options = $profile->toClientOptions();

        // Assert
        self::assertInstanceOf(ClientOptions::class, $options);
        self::assertSame('default', $options->namespace);
    }

    public function testToServiceClientThrowsExceptionWhenAddressIsNull(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: null,
            namespace: 'default',
            apiKey: null,
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address is required to create ServiceClient');

        // Act
        $profile->toServiceClient();
    }

    public function testToServiceClientCreatesClientWithoutTls(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testToServiceClientCreatesClientWithTls(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            tlsConfig: new ConfigTls(disabled: false),
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testToServiceClientCreatesClientWithDisabledTls(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            tlsConfig: new ConfigTls(disabled: true),
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testToServiceClientCreatesClientWithApiKey(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'cloud.temporal.io:7233',
            namespace: 'my-namespace',
            apiKey: 'my-api-key',
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testToServiceClientCreatesClientWithFullTlsConfig(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            tlsConfig: new ConfigTls(
                disabled: false,
                rootCerts: 'ca.pem',
                privateKey: 'key.pem',
                certChain: 'cert.pem',
                serverName: 'custom-server',
            ),
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testToServiceClientCreatesClientWithGrpcMeta(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            grpcMeta: [
                'x-custom-header' => ['custom-value'],
                'authorization' => ['Bearer token'],
            ],
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
        $metadata = $client->getContext()->getMetadata();
        self::assertArrayHasKey('x-custom-header', $metadata);
        self::assertSame(['custom-value'], $metadata['x-custom-header']);
        self::assertArrayHasKey('authorization', $metadata);
        self::assertSame(['Bearer token'], $metadata['authorization']);
    }

    public function testToServiceClientCreatesClientWithAllFeatures(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'cloud.temporal.io:7233',
            namespace: 'my-namespace',
            apiKey: 'my-api-key',
            tlsConfig: new ConfigTls(
                disabled: false,
                rootCerts: 'ca.pem',
                privateKey: 'key.pem',
                certChain: 'cert.pem',
                serverName: 'temporal.cloud',
            ),
            grpcMeta: [
                'x-custom-header' => ['custom-value'],
            ],
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testPropertiesAreReadonly(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: 'test-key',
        );

        // Assert
        $reflection = new \ReflectionClass($profile);

        $addressProperty = $reflection->getProperty('address');
        self::assertTrue($addressProperty->isReadOnly());

        $namespaceProperty = $reflection->getProperty('namespace');
        self::assertTrue($namespaceProperty->isReadOnly());

        $apiKeyProperty = $reflection->getProperty('apiKey');
        self::assertTrue($apiKeyProperty->isReadOnly());

        $tlsConfigProperty = $reflection->getProperty('tlsConfig');
        self::assertTrue($tlsConfigProperty->isReadOnly());

        $grpcMetaProperty = $reflection->getProperty('grpcMeta');
        self::assertTrue($grpcMetaProperty->isReadOnly());

        $codecConfigProperty = $reflection->getProperty('codecConfig');
        self::assertTrue($codecConfigProperty->isReadOnly());
    }

    public function testMergeWithHandlesBothTlsConfigsNull(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            tlsConfig: null,
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            tlsConfig: null,
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertNull($merged->tlsConfig);
    }

    public function testMergeWithHandlesBaseTlsConfigNull(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            tlsConfig: null,
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            tlsConfig: new ConfigTls(disabled: false),
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertInstanceOf(ConfigTls::class, $merged->tlsConfig);
        self::assertFalse($merged->tlsConfig->disabled);
    }

    public function testMergeWithHandlesOverrideTlsConfigNull(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            tlsConfig: new ConfigTls(disabled: true),
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            tlsConfig: null,
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertInstanceOf(ConfigTls::class, $merged->tlsConfig);
        self::assertTrue($merged->tlsConfig->disabled);
    }

    public function testMergeWithHandlesBothCodecConfigsNull(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            codecConfig: null,
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            codecConfig: null,
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertNull($merged->codecConfig);
    }

    public function testMergeWithHandlesBaseCodecConfigNull(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            codecConfig: null,
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            codecConfig: new ConfigCodec(),
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertInstanceOf(ConfigCodec::class, $merged->codecConfig);
    }

    public function testMergeWithHandlesOverrideCodecConfigNull(): void
    {
        // Arrange
        $base = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            codecConfig: new ConfigCodec(),
        );
        $override = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            codecConfig: null,
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertInstanceOf(ConfigCodec::class, $merged->codecConfig);
    }

    public function testConstructorWithEmptyGrpcMeta(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            grpcMeta: [],
        );

        // Assert
        self::assertSame([], $profile->grpcMeta);
    }

    public function testToServiceClientWithEmptyGrpcMeta(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: 'localhost:7233',
            namespace: 'default',
            apiKey: null,
            grpcMeta: [],
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }
}