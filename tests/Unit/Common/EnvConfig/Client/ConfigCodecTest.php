<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common\EnvConfig\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Common\EnvConfig\Client\ConfigCodec;

#[CoversClass(ConfigCodec::class)]
final class ConfigCodecTest extends TestCase
{
    public function testConstructorInitializesProperties(): void
    {
        // Arrange & Act
        $codec = new ConfigCodec(
            endpoint: 'https://codec.example.com',
            auth: 'Bearer token123',
        );

        // Assert
        self::assertSame('https://codec.example.com', $codec->endpoint);
        self::assertSame('Bearer token123', $codec->auth);
    }

    public function testConstructorWithNullValues(): void
    {
        // Arrange & Act
        $codec = new ConfigCodec();

        // Assert
        self::assertNull($codec->endpoint);
        self::assertNull($codec->auth);
    }

    public function testMergeWithOverridesEndpoint(): void
    {
        // Arrange
        $base = new ConfigCodec(
            endpoint: 'https://old.example.com',
            auth: 'Bearer old-token',
        );
        $override = new ConfigCodec(
            endpoint: 'https://new.example.com',
            auth: null,
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame('https://new.example.com', $merged->endpoint);
        self::assertSame('Bearer old-token', $merged->auth);
    }

    public function testMergeWithOverridesAuth(): void
    {
        // Arrange
        $base = new ConfigCodec(
            endpoint: 'https://codec.example.com',
            auth: 'Bearer old-token',
        );
        $override = new ConfigCodec(
            endpoint: null,
            auth: 'Bearer new-token',
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame('https://codec.example.com', $merged->endpoint);
        self::assertSame('Bearer new-token', $merged->auth);
    }

    public function testMergeWithOverridesBothValues(): void
    {
        // Arrange
        $base = new ConfigCodec(
            endpoint: 'https://old.example.com',
            auth: 'Bearer old-token',
        );
        $override = new ConfigCodec(
            endpoint: 'https://new.example.com',
            auth: 'Bearer new-token',
        );

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame('https://new.example.com', $merged->endpoint);
        self::assertSame('Bearer new-token', $merged->auth);
    }

    public function testMergeWithNullOverrideKeepsBaseValues(): void
    {
        // Arrange
        $base = new ConfigCodec(
            endpoint: 'https://codec.example.com',
            auth: 'Bearer token123',
        );
        $override = new ConfigCodec();

        // Act
        $merged = $base->mergeWith($override);

        // Assert
        self::assertSame('https://codec.example.com', $merged->endpoint);
        self::assertSame('Bearer token123', $merged->auth);
    }

    public function testPropertiesAreReadonly(): void
    {
        // Arrange
        $codec = new ConfigCodec(
            endpoint: 'https://codec.example.com',
            auth: 'Bearer token123',
        );

        // Assert
        $reflection = new \ReflectionClass($codec);
        $endpointProperty = $reflection->getProperty('endpoint');
        $authProperty = $reflection->getProperty('auth');

        self::assertTrue($endpointProperty->isReadOnly());
        self::assertTrue($authProperty->isReadOnly());
    }
}
