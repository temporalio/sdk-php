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
use Temporal\Common\EnvConfig\Exception\CodecNotSupportedException;
use Temporal\Common\EnvConfig\Exception\DuplicateProfileException;
use Temporal\Common\EnvConfig\Exception\InvalidConfigException;
use Temporal\Common\EnvConfig\Exception\ProfileNotFoundException;

#[CoversClass(ConfigClient::class)]
#[CoversClass(ConfigProfile::class)]
final class ConfigClientTest extends TestCase
{
    private array $env;

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
        $config = ConfigClient::loadFromToml($toml);

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
        $invalidToml = <<<'TOML'
            [profile.invalid
            address = missing_quote
            TOML;

        // Assert (before Act for exceptions)
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid TOML configuration');

        // Act
        ConfigClient::loadFromToml($invalidToml);
    }

    public function testLoadFromEnvWithSystemEnvProvider(): void
    {
        // Arrange
        $this->env['TEMPORAL_ADDRESS'] = 'localhost:7233';
        $this->env['TEMPORAL_NAMESPACE'] = 'test-namespace';
        $this->env['TEMPORAL_API_KEY'] = 'test-key';

        // Act
        $profile = ConfigClient::loadFromEnv($this->env);

        // Assert
        self::assertInstanceOf(ConfigProfile::class, $profile);
        self::assertSame('localhost:7233', $profile->address);
        self::assertSame('test-namespace', $profile->namespace);
        self::assertSame('test-key', $profile->apiKey);
    }

    public function testLoadFromEnvWithEmptyEnvironment(): void
    {
        // Act
        $profile = ConfigClient::loadFromEnv($this->env);

        // Assert
        self::assertInstanceOf(ConfigProfile::class, $profile);
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
        $profile = ConfigClient::load(
            profileName: 'default',
            configFile: $toml,
        );

        // Assert
        self::assertInstanceOf(ConfigProfile::class, $profile);
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

        $this->env['TEMPORAL_ADDRESS'] = 'override.temporal.io:7233';
        $this->env['TEMPORAL_NAMESPACE'] = 'override-namespace';

        // Act
        $profile = ConfigClient::load(
            profileName: 'default',
            configFile: $toml,
            env: $this->env,
        );

        // Assert
        self::assertInstanceOf(ConfigProfile::class, $profile);
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

        $this->env['TEMPORAL_PROFILE'] = 'production';

        // Act
        $profile = ConfigClient::load(
            profileName: null,
            configFile: $toml,
            env: $this->env,
        );

        // Assert
        self::assertInstanceOf(ConfigProfile::class, $profile);
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
        $profile = ConfigClient::load(
            profileName: null,
            configFile: $toml,
            env: $this->env,
        );

        // Assert
        self::assertInstanceOf(ConfigProfile::class, $profile);
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

    public function testLoadReturnsEmptyProfileWhenDefaultNotFoundAndNotExplicitlyRequested(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.production]
            address = "prod.temporal.io:7233"
            TOML;

        // Act - No profile name specified, no TEMPORAL_PROFILE env var, and no 'default' profile in TOML
        $profile = ConfigClient::load(
            profileName: null,
            configFile: $toml,
            env: $this->env,
        );

        // Assert - Returns empty profile instead of throwing exception (matches Rust behavior)
        self::assertInstanceOf(ConfigProfile::class, $profile);
        self::assertNull($profile->address);
        self::assertNull($profile->namespace);
        self::assertNull($profile->apiKey);
    }

    public function testLoadThrowsExceptionWhenDefaultProfileExplicitlyRequestedButNotFound(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.production]
            address = "prod.temporal.io:7233"
            TOML;

        // Assert (before Act for exceptions)
        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage("Profile 'default' not found");

        // Act - Explicitly requesting 'default' profile that doesn't exist
        ConfigClient::load(
            profileName: 'default',
            configFile: $toml,
            env: $this->env,
        );
    }

    public function testLoadThrowsExceptionWhenProfileRequestedViaEnvVarButNotFound(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.default]
            address = "127.0.0.1:7233"
            TOML;

        $this->env['TEMPORAL_PROFILE'] = 'staging';

        // Assert (before Act for exceptions)
        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage("Profile 'staging' not found");

        // Act - Profile requested via TEMPORAL_PROFILE env var but doesn't exist
        ConfigClient::load(
            profileName: null,
            configFile: $toml,
            env: $this->env,
        );
    }

    public function testLoadFromEnvOnlyWhenNoFileProvided(): void
    {
        // Arrange
        $this->env['TEMPORAL_ADDRESS'] = 'env.temporal.io:7233';
        $this->env['TEMPORAL_NAMESPACE'] = 'env-namespace';

        // Act
        $profile = ConfigClient::load(
            profileName: 'default',
            configFile: null,
            env: $this->env,
        );

        // Assert
        self::assertInstanceOf(ConfigProfile::class, $profile);
        self::assertSame('env.temporal.io:7233', $profile->address);
        self::assertSame('env-namespace', $profile->namespace);
    }

    #[DataProvider('provideCaseInsensitiveProfileNames')]
    public function testGetProfileIsCaseInsensitive(string $storeName, string $accessName): void
    {
        // Arrange
        $toml = "[profile.{$storeName}]\naddress = \"127.0.0.1:7233\"";
        $config = ConfigClient::loadFromToml($toml);

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
        $config = ConfigClient::loadFromToml($toml);

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
        $config = ConfigClient::loadFromToml($toml);

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
        $config = ConfigClient::loadFromToml($toml);

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
        ConfigClient::loadFromToml($toml);
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
        $config = ConfigClient::loadFromToml($toml);

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

    public function testLoadThrowsExceptionWhenCodecIsConfiguredInToml(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [profile.default]
            address = "127.0.0.1:7233"
            [profile.default.codec]
            endpoint = "https://codec.example.com"
            TOML;

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        ConfigClient::load(
            profileName: 'default',
            configFile: $toml,
            env: $this->env,
        );
    }

    public function testLoadThrowsExceptionWhenCodecIsConfiguredInEnv(): void
    {
        // Arrange
        $this->env['TEMPORAL_ADDRESS'] = '127.0.0.1:7233';
        $this->env['TEMPORAL_CODEC_ENDPOINT'] = 'https://codec.example.com';

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        ConfigClient::load(
            profileName: 'default',
            configFile: null,
            env: $this->env,
        );
    }

    public function testLoadFromEnvThrowsExceptionWhenCodecIsConfigured(): void
    {
        // Arrange
        $this->env['TEMPORAL_ADDRESS'] = '127.0.0.1:7233';
        $this->env['TEMPORAL_CODEC_AUTH'] = 'Bearer token123';

        // Assert
        $this->expectException(CodecNotSupportedException::class);
        $this->expectExceptionMessage('Remote codec configuration is not supported in the PHP SDK');

        // Act
        ConfigClient::loadFromEnv($this->env);
    }

    public function testToTomlRoundTripWithSingleProfile(): void
    {
        // Arrange: Load from TOML
        $originalToml = <<<'TOML'
            [profile.default]
            address = "localhost:7233"
            namespace = "default-namespace"
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify profile matches
        self::assertTrue($roundTrip->hasProfile('default'));
        $profile = $roundTrip->getProfile('default');
        self::assertSame('localhost:7233', $profile->address);
        self::assertSame('default-namespace', $profile->namespace);
    }

    public function testToTomlRoundTripWithMultipleProfiles(): void
    {
        // Arrange: Load from TOML with multiple profiles
        $originalToml = <<<'TOML'
            [profile.dev]
            address = "dev.example.com:7233"
            namespace = "dev-namespace"

            [profile.staging]
            address = "staging.example.com:7233"
            namespace = "staging-namespace"
            api_key = "staging-key"

            [profile.prod]
            address = "prod.example.com:7233"
            namespace = "prod-namespace"
            api_key = "prod-key"
            [profile.prod.tls]
            server_name = "prod-server"
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify all profiles match
        self::assertCount(3, $roundTrip->profiles);

        $dev = $roundTrip->getProfile('dev');
        self::assertSame('dev.example.com:7233', $dev->address);
        self::assertSame('dev-namespace', $dev->namespace);
        self::assertNull($dev->apiKey);

        $staging = $roundTrip->getProfile('staging');
        self::assertSame('staging.example.com:7233', $staging->address);
        self::assertSame('staging-namespace', $staging->namespace);
        self::assertSame('staging-key', $staging->apiKey);

        $prod = $roundTrip->getProfile('prod');
        self::assertSame('prod.example.com:7233', $prod->address);
        self::assertSame('prod-namespace', $prod->namespace);
        self::assertSame('prod-key', $prod->apiKey);
        self::assertSame('prod-server', $prod->tlsConfig->serverName);
    }

    public function testToTomlRoundTripWithComplexTlsConfig(): void
    {
        // Arrange: Load profile with full TLS configuration
        $originalToml = <<<'TOML'
            [profile.secure]
            address = "secure.example.com:7233"
            namespace = "secure-namespace"
            [profile.secure.tls]
            server_ca_cert_data = "ca-cert-content"
            client_cert_data = "client-cert-content"
            client_key_data = "client-key-content"
            server_name = "custom-server"
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify TLS configuration is preserved
        $profile = $roundTrip->getProfile('secure');
        self::assertSame('secure.example.com:7233', $profile->address);
        self::assertSame('secure-namespace', $profile->namespace);
        self::assertInstanceOf(ConfigTls::class, $profile->tlsConfig);
        self::assertSame('ca-cert-content', $profile->tlsConfig->rootCerts);
        self::assertSame('client-cert-content', $profile->tlsConfig->certChain);
        self::assertSame('client-key-content', $profile->tlsConfig->privateKey);
        self::assertSame('custom-server', $profile->tlsConfig->serverName);
    }

    public function testToTomlRoundTripWithGrpcMetadata(): void
    {
        // Arrange: Load profile with gRPC metadata
        $originalToml = <<<'TOML'
            [profile.with_meta]
            address = "meta.example.com:7233"
            namespace = "meta-namespace"
            [profile.with_meta.grpc_meta]
            authorization = "Bearer token123"
            x-custom-header = "custom-value"
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify gRPC metadata is preserved
        $profile = $roundTrip->getProfile('with_meta');
        self::assertSame('meta.example.com:7233', $profile->address);
        self::assertSame('meta-namespace', $profile->namespace);
        self::assertArrayHasKey('authorization', $profile->grpcMeta);
        self::assertSame(['Bearer token123'], $profile->grpcMeta['authorization']);
        self::assertArrayHasKey('x-custom-header', $profile->grpcMeta);
        self::assertSame(['custom-value'], $profile->grpcMeta['x-custom-header']);
    }

    public function testToTomlRoundTripWithTlsDisabled(): void
    {
        // Arrange: Load profile with explicitly disabled TLS
        $originalToml = <<<'TOML'
            [profile.no_tls]
            address = "localhost:7233"
            namespace = "local"
            [profile.no_tls.tls]
            disabled = true
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify TLS remains disabled
        $profile = $roundTrip->getProfile('no_tls');
        self::assertSame('localhost:7233', $profile->address);
        self::assertTrue($profile->tlsConfig->disabled);
    }

    public function testToTomlRoundTripWithApiKeyEnablesTls(): void
    {
        // Arrange: Load profile with API key (auto-enables TLS)
        $originalToml = <<<'TOML'
            [profile.cloud]
            address = "cloud.example.com:7233"
            namespace = "cloud-namespace"
            api_key = "cloud-api-key"
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify TLS is enabled and API key is preserved
        $profile = $roundTrip->getProfile('cloud');
        self::assertSame('cloud.example.com:7233', $profile->address);
        self::assertSame('cloud-namespace', $profile->namespace);
        self::assertSame('cloud-api-key', $profile->apiKey);
        self::assertFalse($profile->tlsConfig->disabled);
    }

    public function testToTomlRoundTripPreservesCaseInsensitiveProfileNames(): void
    {
        // Arrange: Load profiles with various case names
        $originalToml = <<<'TOML'
            [profile.Default]
            address = "default.example.com:7233"

            [profile.PRODUCTION]
            address = "prod.example.com:7233"

            [profile.MixedCase]
            address = "mixed.example.com:7233"
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: All profiles accessible (case-insensitive)
        self::assertCount(3, $roundTrip->profiles);
        self::assertTrue($roundTrip->hasProfile('default'));
        self::assertTrue($roundTrip->hasProfile('Default'));
        self::assertTrue($roundTrip->hasProfile('production'));
        self::assertTrue($roundTrip->hasProfile('PRODUCTION'));
        self::assertTrue($roundTrip->hasProfile('mixedcase'));
        self::assertTrue($roundTrip->hasProfile('MixedCase'));
    }

    public function testToTomlRoundTripWithMinimalProfile(): void
    {
        // Arrange: Load minimal profile (just address)
        $originalToml = <<<'TOML'
            [profile.minimal]
            address = "minimal.example.com:7233"
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify minimal profile is preserved
        $profile = $roundTrip->getProfile('minimal');
        self::assertSame('minimal.example.com:7233', $profile->address);
        self::assertNull($profile->namespace);
        self::assertNull($profile->apiKey);
        self::assertTrue($profile->tlsConfig->disabled);
    }

    public function testToTomlRoundTripWithEmptyGrpcMeta(): void
    {
        // Arrange: Load profile with empty grpc_meta section
        $originalToml = <<<'TOML'
            [profile.empty_meta]
            address = "empty.example.com:7233"
            [profile.empty_meta.grpc_meta]
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify empty metadata is preserved
        $profile = $roundTrip->getProfile('empty_meta');
        self::assertSame('empty.example.com:7233', $profile->address);
        self::assertSame([], $profile->grpcMeta);
    }

    public function testToTomlRoundTripWithAllFeatures(): void
    {
        // Arrange: Load profile with all possible features
        $originalToml = <<<'TOML'
            [profile.full]
            address = "full.example.com:7233"
            namespace = "full-namespace"
            api_key = "full-api-key"
            [profile.full.tls]
            server_ca_cert_data = "full-ca-data"
            client_cert_data = "full-cert-data"
            client_key_data = "full-key-data"
            server_name = "full-server"
            [profile.full.grpc_meta]
            authorization = "Bearer full-token"
            x-custom-1 = "value1"
            x-custom-2 = "value2"
            TOML;
        $original = ConfigClient::loadFromToml($originalToml);

        // Act: Convert to TOML and reload
        $tomlString = $original->toToml();
        $roundTrip = ConfigClient::loadFromToml($tomlString);

        // Assert: Verify all features are preserved
        $profile = $roundTrip->getProfile('full');

        // Basic fields
        self::assertSame('full.example.com:7233', $profile->address);
        self::assertSame('full-namespace', $profile->namespace);
        self::assertSame('full-api-key', $profile->apiKey);

        // TLS config
        self::assertFalse($profile->tlsConfig->disabled);
        self::assertSame('full-ca-data', $profile->tlsConfig->rootCerts);
        self::assertSame('full-cert-data', $profile->tlsConfig->certChain);
        self::assertSame('full-key-data', $profile->tlsConfig->privateKey);
        self::assertSame('full-server', $profile->tlsConfig->serverName);

        // gRPC metadata
        self::assertCount(3, $profile->grpcMeta);
        self::assertSame(['Bearer full-token'], $profile->grpcMeta['authorization']);
        self::assertSame(['value1'], $profile->grpcMeta['x-custom-1']);
        self::assertSame(['value2'], $profile->grpcMeta['x-custom-2']);
    }

    protected function setUp(): void
    {
        // Arrange (common setup)
        $this->env = [];
    }
}
