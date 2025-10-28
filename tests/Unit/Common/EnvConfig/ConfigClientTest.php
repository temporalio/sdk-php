<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common\EnvConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Common\EnvConfig\Client\ConfigProfile;
use Temporal\Common\EnvConfig\Client\ConfigTls;
use Temporal\Common\EnvConfig\ConfigClient;
use Temporal\Common\EnvConfig\Exception\DuplicateProfileException;
use Temporal\Common\EnvConfig\Exception\InvalidConfigException;
use Temporal\Common\EnvConfig\Exception\ProfileNotFoundException;
use Temporal\Tests\Unit\Common\EnvConfig\Client\Stub\ArrayEnvProvider;

#[CoversClass(ConfigClient::class)]
#[CoversClass(ConfigProfile::class)]
final class ConfigClientTest extends TestCase
{
    private ArrayEnvProvider $envProvider;

    public static function provideCaseInsensitiveProfileNames(): \Generator
    {
        yield 'exact match' => ['default', 'default'];
        yield 'uppercase access' => ['default', 'DEFAULT'];
        yield 'mixed case access' => ['production', 'PrOdUcTiOn'];
        yield 'lowercase access' => ['Production', 'production'];
    }

    public function testLoadFromFileWithValidTomlContent(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"
namespace = "default"

[profile.production]
address = "prod.temporal.io:7233"
namespace = "production"
TOML;

        // Act
        $config = ConfigClient::loadFromFile($toml);

        // Assert
        self::assertTrue($config->hasProfile('default'));
        self::assertTrue($config->hasProfile('production'));

        $defaultProfile = $config->getProfile('default');
        self::assertSame('127.0.0.1:7233', $defaultProfile->address);
        self::assertSame('default', $defaultProfile->namespace);

        $prodProfile = $config->getProfile('production');
        self::assertSame('prod.temporal.io:7233', $prodProfile->address);
        self::assertSame('production', $prodProfile->namespace);
    }

    public function testLoadFromFileThrowsExceptionForInvalidToml(): void
    {
        // Arrange
        $invalidToml = '[profile.invalid
address = missing_quote';

        // Assert (before Act for exceptions)
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid TOML configuration');

        // Act
        ConfigClient::loadFromFile($invalidToml);
    }

    public function testLoadFromEnvWithSystemEnvProvider(): void
    {
        // Arrange
        $this->envProvider->set('TEMPORAL_ADDRESS', 'localhost:7233');
        $this->envProvider->set('TEMPORAL_NAMESPACE', 'test-namespace');
        $this->envProvider->set('TEMPORAL_API_KEY', 'test-key');

        // Act
        $config = ConfigClient::loadFromEnv($this->envProvider);

        // Assert
        self::assertInstanceOf(ConfigClient::class, $config);
        $profile = $config->getProfile('default');
        self::assertSame('localhost:7233', $profile->address);
        self::assertSame('test-namespace', $profile->namespace);
        self::assertSame('test-key', $profile->apiKey);
    }

    public function testLoadFromEnvWithEmptyEnvironment(): void
    {
        // Act
        $config = ConfigClient::loadFromEnv($this->envProvider);

        // Assert
        self::assertInstanceOf(ConfigClient::class, $config);
        $profile = $config->getProfile('default');
        self::assertNull($profile->address);
        self::assertNull($profile->namespace);
        self::assertNull($profile->apiKey);
    }

    public function testLoadFromFileOnly(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"
namespace = "default"
TOML;

        // Act
        $config = ConfigClient::load(
            profileName: 'default',
            configFile: $toml,
        );

        // Assert
        $profile = $config->getProfile('default');
        self::assertSame('127.0.0.1:7233', $profile->address);
        self::assertSame('default', $profile->namespace);
    }

    public function testLoadWithEnvOverrides(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"
namespace = "default"
TOML;

        $this->envProvider->set('TEMPORAL_ADDRESS', 'override.temporal.io:7233');
        $this->envProvider->set('TEMPORAL_NAMESPACE', 'override-namespace');

        // Act
        $config = ConfigClient::load(
            profileName: 'default',
            configFile: $toml,
            envProvider: $this->envProvider,
        );

        // Assert
        $profile = $config->getProfile('default');
        self::assertSame('override.temporal.io:7233', $profile->address);
        self::assertSame('override-namespace', $profile->namespace);
    }

    public function testLoadUsesTemporalProfileEnvVar(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"

[profile.production]
address = "prod.temporal.io:7233"
TOML;

        $this->envProvider->set('TEMPORAL_PROFILE', 'production');

        // Act
        $config = ConfigClient::load(
            profileName: null,
            configFile: $toml,
            envProvider: $this->envProvider,
        );

        // Assert
        $profile = $config->getProfile('production');
        self::assertSame('prod.temporal.io:7233', $profile->address);
    }

    public function testLoadDefaultsToDefaultProfile(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"
TOML;

        // Act
        $config = ConfigClient::load(
            profileName: null,
            configFile: $toml,
            envProvider: $this->envProvider,
        );

        // Assert
        $profile = $config->getProfile('default');
        self::assertSame('127.0.0.1:7233', $profile->address);
    }

    public function testLoadThrowsExceptionWhenProfileNotFound(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"
TOML;

        // Assert (before Act for exceptions)
        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage("Profile 'production' not found");

        // Act
        ConfigClient::load(
            profileName: 'production',
            configFile: $toml,
        );
    }

    public function testLoadFromEnvOnlyWhenNoFileProvided(): void
    {
        // Arrange
        $this->envProvider->set('TEMPORAL_ADDRESS', 'env.temporal.io:7233');
        $this->envProvider->set('TEMPORAL_NAMESPACE', 'env-namespace');

        // Act
        $config = ConfigClient::load(
            profileName: 'default',
            configFile: null,
            envProvider: $this->envProvider,
        );

        // Assert
        $profile = $config->getProfile('default');
        self::assertSame('env.temporal.io:7233', $profile->address);
        self::assertSame('env-namespace', $profile->namespace);
    }

    #[DataProvider('provideCaseInsensitiveProfileNames')]
    public function testGetProfileIsCaseInsensitive(string $storeName, string $accessName): void
    {
        // Arrange
        $toml = "[profile.{$storeName}]\naddress = \"127.0.0.1:7233\"";
        $config = ConfigClient::loadFromFile($toml);

        // Act
        $profile = $config->getProfile($accessName);

        // Assert
        self::assertSame('127.0.0.1:7233', $profile->address);
    }

    #[DataProvider('provideCaseInsensitiveProfileNames')]
    public function testHasProfileIsCaseInsensitive(string $storeName, string $accessName): void
    {
        // Arrange
        $toml = "[profile.{$storeName}]\naddress = \"127.0.0.1:7233\"";
        $config = ConfigClient::loadFromFile($toml);

        // Act & Assert
        self::assertTrue($config->hasProfile($accessName));
    }

    public function testGetProfileThrowsExceptionWhenNotFound(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"
TOML;
        $config = ConfigClient::loadFromFile($toml);

        // Assert (before Act for exceptions)
        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage("Profile 'nonexistent' not found");

        // Act
        $config->getProfile('nonexistent');
    }

    public function testHasProfileReturnsFalseWhenNotFound(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"
TOML;
        $config = ConfigClient::loadFromFile($toml);

        // Act & Assert
        self::assertFalse($config->hasProfile('nonexistent'));
    }

    public function testDuplicateCaseInsensitiveProfileNamesThrowException(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"

[profile.Default]
address = "other.temporal.io:7233"
TOML;

        // Assert (before Act for exceptions)
        $this->expectException(DuplicateProfileException::class);
        $this->expectExceptionMessage("Duplicate profile name (case-insensitive): 'Default' conflicts with existing 'default'");

        // Act
        ConfigClient::loadFromFile($toml);
    }

    public function testToServiceClientWithoutTls(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test',
            apiKey: null,
            tlsConfig: new ConfigTls(disabled: true),
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testToServiceClientWithTls(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test',
            apiKey: null,
            tlsConfig: new ConfigTls(
                disabled: false,
                rootCerts: null,
                privateKey: null,
                certChain: null,
                serverName: 'temporal.example.com',
            ),
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testToServiceClientWithApiKey(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test',
            apiKey: 'test-api-key',
            tlsConfig: new ConfigTls(disabled: false),
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
    }

    public function testToServiceClientThrowsExceptionWithoutAddress(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: null,
            namespace: 'test',
            apiKey: null,
        );

        // Assert (before Act for exceptions)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address is required to create ServiceClient');

        // Act
        $profile->toServiceClient();
    }

    public function testToClientOptionsWithNamespace(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'custom-namespace',
            apiKey: null,
        );

        // Act
        $options = $profile->toClientOptions();

        // Assert
        self::assertInstanceOf(ClientOptions::class, $options);
        self::assertSame('custom-namespace', $options->namespace);
    }

    public function testToClientOptionsWithoutNamespace(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: null,
            apiKey: null,
        );

        // Act
        $options = $profile->toClientOptions();

        // Assert
        self::assertInstanceOf(ClientOptions::class, $options);
        self::assertSame(ClientOptions::DEFAULT_NAMESPACE, $options->namespace);
    }

    public function testConfigProfileNormalizesEmptyStringsToNull(): void
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

    public function testConfigProfileKeepsNonEmptyStrings(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test-namespace',
            apiKey: 'test-key',
        );

        // Assert
        self::assertSame('127.0.0.1:7233', $profile->address);
        self::assertSame('test-namespace', $profile->namespace);
        self::assertSame('test-key', $profile->apiKey);
    }

    public function testProfilesPropertyIsReadonly(): void
    {
        // Arrange
        $toml = <<<'TOML'
[profile.default]
address = "127.0.0.1:7233"
TOML;
        $config = ConfigClient::loadFromFile($toml);

        // Assert
        self::assertIsArray($config->profiles);
        self::assertCount(1, $config->profiles);
        self::assertArrayHasKey('default', $config->profiles);
    }

    public function testGrpcMetadataKeysNormalizedToLowercase(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test',
            apiKey: null,
            grpcMeta: [
                'temporal-namespace' => 'value1',
                'TEMPORAL_CLIENT' => 'value2',
                'Custom_Header' => 'value3',
            ],
        );

        // Assert
        self::assertArrayHasKey('temporal-namespace', $profile->grpcMeta);
        self::assertArrayHasKey('temporal_client', $profile->grpcMeta);
        self::assertArrayHasKey('custom_header', $profile->grpcMeta);
        self::assertSame(['value1'], $profile->grpcMeta['temporal-namespace']);
        self::assertSame(['value2'], $profile->grpcMeta['temporal_client']);
        self::assertSame(['value3'], $profile->grpcMeta['custom_header']);
    }

    public function testGrpcMetadataStringValuesConvertedToArrays(): void
    {
        // Arrange & Act
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test',
            apiKey: null,
            grpcMeta: [
                'String-Value' => 'single',
                'Array-Value' => ['multiple', 'values'],
            ],
        );

        // Assert
        self::assertSame(['single'], $profile->grpcMeta['string-value']);
        self::assertSame(['multiple', 'values'], $profile->grpcMeta['array-value']);
    }

    public function testGrpcMetadataMergeWithNormalization(): void
    {
        // Arrange
        $profile1 = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test',
            apiKey: null,
            grpcMeta: [
                'temporal-namespace' => 'value1',
                'custom-header' => 'base',
            ],
        );

        $profile2 = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            grpcMeta: [
                'TEMPORAL_NAMESPACE' => 'value2',  // Same key, different case - should replace
                'New-Header' => 'new',
            ],
        );

        // Act
        $merged = $profile1->mergeWith($profile2);

        // Assert
        self::assertArrayHasKey('temporal_namespace', $merged->grpcMeta);
        self::assertArrayHasKey('custom-header', $merged->grpcMeta);
        self::assertArrayHasKey('new-header', $merged->grpcMeta);
        // Values should be replaced for same key (case-insensitive)
        self::assertSame(['value2'], $merged->grpcMeta['temporal_namespace']);
        self::assertSame(['base'], $merged->grpcMeta['custom-header']);
        self::assertSame(['new'], $merged->grpcMeta['new-header']);
    }

    public function testGrpcMetadataMergeReplacesValues(): void
    {
        // Arrange
        $profile1 = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test',
            apiKey: null,
            grpcMeta: [
                'Header-One' => ['base1', 'base2'],
            ],
        );

        $profile2 = new ConfigProfile(
            address: null,
            namespace: null,
            apiKey: null,
            grpcMeta: [
                'header-one' => ['override1'],  // Same key, lowercase - should replace completely
            ],
        );

        // Act
        $merged = $profile1->mergeWith($profile2);

        // Assert
        self::assertArrayHasKey('header-one', $merged->grpcMeta);
        self::assertSame(['override1'], $merged->grpcMeta['header-one']);
    }

    public function testToServiceClientWithGrpcMetadata(): void
    {
        // Arrange
        $profile = new ConfigProfile(
            address: '127.0.0.1:7233',
            namespace: 'test',
            apiKey: null,
            grpcMeta: [
                'custom-header' => 'custom-value',
            ],
        );

        // Act
        $client = $profile->toServiceClient();

        // Assert
        self::assertInstanceOf(ServiceClient::class, $client);
        $context = $client->getContext();
        $metadata = $context->getMetadata();
        self::assertArrayHasKey('custom-header', $metadata);
        self::assertSame(['custom-value'], $metadata['custom-header']);
    }

    protected function setUp(): void
    {
        // Arrange (common setup)
        $this->envProvider = new ArrayEnvProvider();
    }
}
