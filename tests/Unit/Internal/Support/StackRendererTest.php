<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Sdk\V1\EnhancedStackTrace;
use Temporal\Api\Sdk\V1\StackTrace;
use Temporal\Api\Sdk\V1\StackTraceFileLocation;
use Temporal\Api\Sdk\V1\StackTraceFileSlice;
use Temporal\Api\Sdk\V1\StackTraceSDKInfo;
use Temporal\Common\SdkVersion;
use Temporal\Internal\Support\StackRenderer;

#[RunTestsInSeparateProcesses]
#[CoversClass(StackRenderer::class)]
final class StackRendererTest extends TestCase
{
    public static function provideStackTraceWithDifferentPathTypes(): \Generator
    {
        yield 'directory with trailing slash' => [
            '/vendor/',
            [
                ['file' => '/vendor/package/file.php', 'line' => 10],
                ['file' => '/app/file.php', 'line' => 20],
            ],
            '/app/file.php:20',
        ];

        yield 'specific file path' => [
            '/vendor/specific.php',
            [
                ['file' => '/vendor/specific.php', 'line' => 10],
                ['file' => '/vendor/other.php', 'line' => 15],
                ['file' => '/app/file.php', 'line' => 20],
            ],
            "/vendor/other.php:15\n/app/file.php:20",
        ];

        yield 'Windows-style paths' => [
            'C:\\vendor\\',
            [
                ['file' => 'C:\\vendor\\package\\file.php', 'line' => 10],
                ['file' => 'C:\\app\\file.php', 'line' => 20],
            ],
            'C:\\app\\file.php:20',
        ];
    }

    public static function provideFunctionNameCombinations(): \Generator
    {
        yield 'function only' => [
            ['function' => 'testFunction'],
            'testFunction',
        ];

        yield 'static method call' => [
            ['class' => 'TestClass', 'type' => '::', 'function' => 'staticMethod'],
            'TestClass::staticMethod',
        ];

        yield 'instance method call' => [
            ['class' => 'TestClass', 'type' => '->', 'function' => 'instanceMethod'],
            'TestClass->instanceMethod',
        ];

        yield 'class without type' => [
            ['class' => 'TestClass', 'function' => 'method'],
            'TestClassmethod',
        ];

        yield 'type without class' => [
            ['type' => '::', 'function' => 'method'],
            '::method',
        ];
    }

    #[DataProvider('provideStackTraceWithDifferentPathTypes')]
    public function testRenderStringWithDifferentPathTypes(
        string $ignoredPath,
        array $stackTrace,
        string $expectedOutput,
    ): void {
        // Act
        StackRenderer::addIgnoredPath($ignoredPath);
        $result = StackRenderer::renderString($stackTrace);

        // Assert
        self::assertSame($expectedOutput, $result);
    }

    #[DataProvider('provideFunctionNameCombinations')]
    public function testRenderProtoGeneratesFunctionNames(
        array $stackFrame,
        string $expectedFunctionName,
    ): void {
        // Arrange
        $stackTrace = [$stackFrame];

        // Act
        $result = StackRenderer::renderProto($stackTrace);

        // Assert
        $location = $result->getStacks()[0]->getLocations()[0];
        self::assertSame($expectedFunctionName, $location->getFunctionName());
    }

    public function testAddIgnoredPathAddsPathToIgnoreList(): void
    {
        // Arrange
        $ignoredPath = '/vendor/path/';
        $stackTrace = [
            ['file' => '/vendor/path/file.php', 'line' => 10],
            ['file' => '/app/file.php', 'line' => 20],
        ];

        // Act
        StackRenderer::addIgnoredPath($ignoredPath);
        $result = StackRenderer::renderString($stackTrace);

        // Assert
        $expected = '/app/file.php:20';
        self::assertSame($expected, $result);
    }

    public function testRenderStringWithEmptyStackTrace(): void
    {
        // Arrange
        $stackTrace = [];

        // Act
        $result = StackRenderer::renderString($stackTrace);

        // Assert
        self::assertSame('', $result);
    }

    public function testRenderStringWithStackTraceWithoutFiles(): void
    {
        // Arrange
        $stackTrace = [
            ['function' => 'test', 'class' => 'TestClass'],
            ['function' => 'another'],
        ];

        // Act
        $result = StackRenderer::renderString($stackTrace);

        // Assert
        self::assertSame('', $result);
    }

    public function testRenderStringWithBasicStackTrace(): void
    {
        // Arrange
        $stackTrace = [
            ['file' => '/app/file1.php', 'line' => 10],
            ['file' => '/app/file2.php', 'line' => 20],
            ['file' => '/app/file3.php'],
        ];

        // Act
        $result = StackRenderer::renderString($stackTrace);

        // Assert
        $expected = "/app/file1.php:10\n/app/file2.php:20\n/app/file3.php:-";
        self::assertSame($expected, $result);
    }

    public function testRenderStringWithInternalFramesSkipped(): void
    {
        // Arrange
        $internalPath = '/internal/';
        $stackTrace = [
            ['file' => '/app/file1.php', 'line' => 10],
            ['file' => '/internal/file1.php', 'line' => 15],
            ['file' => '/internal/file2.php', 'line' => 25],
            ['file' => '/app/file2.php', 'line' => 20],
        ];

        // Act
        StackRenderer::addIgnoredPath($internalPath);
        $result = StackRenderer::renderString($stackTrace);

        // Assert
        $expected = "/app/file1.php:10\n  ... skipped 2 internal frames\n/app/file2.php:20";
        self::assertSame($expected, $result);
    }

    public function testRenderStringWithConsecutiveInternalFrames(): void
    {
        // Arrange
        $internalPath1 = '/vendor/';
        $internalPath2 = '/internal/';
        $stackTrace = [
            ['file' => '/vendor/file1.php', 'line' => 10],
            ['file' => '/internal/file1.php', 'line' => 15],
            ['file' => '/app/file1.php', 'line' => 20],
        ];

        // Act
        StackRenderer::addIgnoredPath($internalPath1);
        StackRenderer::addIgnoredPath($internalPath2);
        $result = StackRenderer::renderString($stackTrace);

        // Assert
        $expected = "/app/file1.php:20";
        self::assertSame($expected, $result);
    }

    public function testRenderProtoWithEmptyStackTrace(): void
    {
        // Arrange
        $stackTrace = [];

        // Act
        $result = StackRenderer::renderProto($stackTrace);

        // Assert
        self::assertInstanceOf(EnhancedStackTrace::class, $result);
        self::assertInstanceOf(StackTraceSDKInfo::class, $result->getSdk());
        self::assertSame(SdkVersion::SDK_NAME, $result->getSdk()->getName());
        self::assertSame(SdkVersion::getSdkVersion(), $result->getSdk()->getVersion());
        self::assertCount(0, $result->getSources());
        self::assertCount(1, $result->getStacks());
        self::assertCount(0, $result->getStacks()[0]->getLocations());
    }

    public function testRenderProtoWithBasicStackTrace(): void
    {
        // Arrange
        $stackTrace = [
            [
                'file' => __FILE__,
                'line' => 123,
                'function' => 'testMethod',
                'class' => 'TestClass',
                'type' => '::',
            ],
            [
                'file' => '/another/file.php',
                'line' => 456,
                'function' => 'anotherMethod',
            ],
        ];

        // Act
        $result = StackRenderer::renderProto($stackTrace);

        // Assert
        self::assertInstanceOf(EnhancedStackTrace::class, $result);

        // Check SDK info
        $sdk = $result->getSdk();
        self::assertSame(SdkVersion::SDK_NAME, $sdk->getName());
        self::assertSame(SdkVersion::getSdkVersion(), $sdk->getVersion());

        // Check sources contain the readable file
        $sources = $result->getSources();
        self::assertArrayHasKey(__FILE__, $sources);
        self::assertInstanceOf(StackTraceFileSlice::class, $sources[__FILE__]);
        self::assertSame(0, $sources[__FILE__]->getLineOffset());
        self::assertStringContainsString('<?php', $sources[__FILE__]->getContent());

        // Check stack traces
        $stacks = $result->getStacks();
        self::assertCount(1, $stacks);
        self::assertInstanceOf(StackTrace::class, $stacks[0]);

        // Check locations
        $locations = $stacks[0]->getLocations();
        self::assertCount(2, $locations);

        // First location
        $firstLocation = $locations[0];
        self::assertInstanceOf(StackTraceFileLocation::class, $firstLocation);
        self::assertSame(__FILE__, $firstLocation->getFilePath());
        self::assertSame(123, $firstLocation->getLine());
        self::assertSame('TestClass::testMethod', $firstLocation->getFunctionName());
        self::assertFalse($firstLocation->getInternalCode());

        // Second location
        $secondLocation = $locations[1];
        self::assertSame('/another/file.php', $secondLocation->getFilePath());
        self::assertSame(456, $secondLocation->getLine());
        self::assertSame('anotherMethod', $secondLocation->getFunctionName());
        self::assertFalse($secondLocation->getInternalCode());
    }

    public function testRenderProtoWithInternalCode(): void
    {
        // Arrange
        $internalPath = '/internal/';
        $stackTrace = [
            ['file' => '/internal/file.php', 'line' => 10, 'function' => 'internalMethod'],
            ['file' => '/app/file.php', 'line' => 20, 'function' => 'appMethod'],
        ];

        // Act
        StackRenderer::addIgnoredPath($internalPath);
        $result = StackRenderer::renderProto($stackTrace);

        // Assert
        $locations = $result->getStacks()[0]->getLocations();
        self::assertCount(2, $locations);

        // Internal location should be marked as internal
        self::assertTrue($locations[0]->getInternalCode());
        self::assertFalse($locations[1]->getInternalCode());

        // Sources should not contain internal files
        $sources = $result->getSources();
        self::assertArrayNotHasKey('/internal/file.php', $sources);
    }

    public function testRenderProtoWithMissingFileInformation(): void
    {
        // Arrange
        $stackTrace = [
            ['function' => 'someFunction'],
            ['line' => 123, 'function' => 'anotherFunction'],
        ];

        // Act
        $result = StackRenderer::renderProto($stackTrace);

        // Assert
        $locations = $result->getStacks()[0]->getLocations();
        self::assertCount(2, $locations);

        // First location without file
        $firstLocation = $locations[0];
        self::assertSame('', $firstLocation->getFilePath());
        self::assertSame(0, $firstLocation->getLine());
        self::assertSame('someFunction', $firstLocation->getFunctionName());

        // Second location with line but no file
        $secondLocation = $locations[1];
        self::assertSame('', $secondLocation->getFilePath());
        self::assertSame(123, $secondLocation->getLine());
        self::assertSame('anotherFunction', $secondLocation->getFunctionName());
    }

    public function testRenderProtoHandlesUnreadableFiles(): void
    {
        // Arrange
        $nonExistentFile = '/path/to/nonexistent/file.php';
        $stackTrace = [
            ['file' => $nonExistentFile, 'line' => 10, 'function' => 'test'],
        ];

        // Act
        $result = StackRenderer::renderProto($stackTrace);

        // Assert
        $sources = $result->getSources();
        self::assertArrayHasKey($nonExistentFile, $sources);

        $fileSlice = $sources[$nonExistentFile];
        self::assertStringContainsString('Failed to read file', $fileSlice->getContent());
    }

    public function testInitializationAddsTemporalInternalPath(): void
    {
        // Arrange
        $temporalInternalFile = \implode(DIRECTORY_SEPARATOR, [\dirname(__DIR__, 4), 'src', 'Internal', 'file.php']);
        $stackTrace = [
            ['file' => $temporalInternalFile, 'line' => 10],
            ['file' => '/app/file.php', 'line' => 20],
        ];

        // Act
        $result = StackRenderer::renderString($stackTrace);

        // Assert - Temporal internal files should be skipped by default
        $expected = "/app/file.php:20";
        self::assertSame($expected, $result);
    }
}
