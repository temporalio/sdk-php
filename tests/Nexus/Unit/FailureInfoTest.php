<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\FailureInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FailureInfo::class)]
final class FailureInfoTest extends TestCase
{
    public function testWithAllFields(): void
    {
        $failure = new FailureInfo(
            message: 'Test failure message',
            stackTrace: 'at Test.method(Test.java:10)',
            metadata: ['key1' => 'value1', 'key2' => 'value2'],
            detailsJson: '{"detail": "value"}',
        );

        self::assertSame('Test failure message', $failure->message);
        self::assertSame('at Test.method(Test.java:10)', $failure->stackTrace);
        self::assertCount(2, $failure->metadata);
        self::assertSame('value1', $failure->metadata['key1']);
        self::assertSame('value2', $failure->metadata['key2']);
        self::assertSame('{"detail": "value"}', $failure->detailsJson);
    }

    public function testWithNullStackTrace(): void
    {
        $failure = new FailureInfo(
            message: 'Test failure message',
            stackTrace: null,
        );

        self::assertSame('Test failure message', $failure->message);
        self::assertNull($failure->stackTrace);
    }

    public function testWithoutStackTrace(): void
    {
        $failure = new FailureInfo(message: 'Test failure message');

        self::assertSame('Test failure message', $failure->message);
        self::assertNull($failure->stackTrace);
    }

    public function testCopyWithModifiedFields(): void
    {
        $original = new FailureInfo(
            message: 'Original message',
            stackTrace: 'Original stack trace',
            metadata: ['key' => 'value'],
            detailsJson: '{"original": true}',
        );

        $copied = new FailureInfo(
            message: 'Updated message',
            stackTrace: $original->stackTrace,
            metadata: \array_merge($original->metadata, ['newKey' => 'newValue']),
            detailsJson: $original->detailsJson,
        );

        self::assertSame('Updated message', $copied->message);
        self::assertSame('Original stack trace', $copied->stackTrace);
        self::assertCount(2, $copied->metadata);
        self::assertSame('value', $copied->metadata['key']);
        self::assertSame('newValue', $copied->metadata['newKey']);
        self::assertSame('{"original": true}', $copied->detailsJson);
    }

    public function testEquality(): void
    {
        $failure1 = new FailureInfo(
            message: 'message',
            stackTrace: 'stack',
            metadata: ['key' => 'value'],
            detailsJson: '{}',
        );

        $failure2 = new FailureInfo(
            message: 'message',
            stackTrace: 'stack',
            metadata: ['key' => 'value'],
            detailsJson: '{}',
        );

        $failure3 = new FailureInfo(
            message: 'different',
            stackTrace: 'stack',
            metadata: ['key' => 'value'],
        );

        self::assertEquals($failure1, $failure2);
        self::assertNotEquals($failure1, $failure3);
    }

    public function testToStringContainsAllFields(): void
    {
        $failure = new FailureInfo(
            message: 'test message',
            stackTrace: 'test stack',
            metadata: ['key' => 'value'],
            detailsJson: '{}',
        );

        $str = (string) $failure;
        self::assertStringStartsWith('FailureInfo{', $str);
        self::assertStringContainsString('test message', $str);
        self::assertStringContainsString('test stack', $str);
        self::assertStringContainsString('key', $str);
        self::assertStringContainsString('value', $str);
        self::assertStringContainsString('{}', $str);
    }

    public function testCauseIsOptional(): void
    {
        $info = new FailureInfo(message: 'outer');
        self::assertNull($info->cause);
    }

    public function testCauseStoresChain(): void
    {
        $root = new FailureInfo(message: 'root');
        $middle = new FailureInfo(message: 'middle', cause: $root);
        $outer = new FailureInfo(message: 'outer', cause: $middle);

        self::assertSame($middle, $outer->cause);
        self::assertSame($root, $outer->cause->cause);
        self::assertNull($outer->cause->cause->cause);
    }

    public function testFromThrowableBuildsChain(): void
    {
        $root = new \RuntimeException('root');
        $middle = new \LogicException('middle', 0, $root);
        $outer = new \Exception('outer', 0, $middle);

        $info = FailureInfo::fromThrowable($outer);

        self::assertSame('outer', $info->message);
        self::assertSame('middle', $info->cause->message);
        self::assertSame('root', $info->cause->cause->message);
        self::assertNull($info->cause->cause->cause);
    }

    public function testFromThrowableRespectsMaxDepth(): void
    {
        // Synthetic chain of 5 exceptions: level-1 -> level-2 -> ... -> level-5
        $deepest = new \Exception('level-5');
        $e = $deepest;
        for ($i = 4; $i >= 1; $i--) {
            $e = new \Exception("level-{$i}", 0, $e);
        }

        $info = FailureInfo::fromThrowable($e, maxDepth: 2);
        self::assertSame('level-1', $info->message);
        self::assertSame('level-2', $info->cause->message);
        self::assertSame('level-3', $info->cause->cause->message);
        // depth exhausted — level-4 and level-5 dropped
        self::assertNull($info->cause->cause->cause);
    }

    public function testToStringIncludesCause(): void
    {
        $info = new FailureInfo(message: 'outer', cause: new FailureInfo(message: 'inner'));
        $s = (string) $info;
        self::assertStringContainsString("message='outer'", $s);
        self::assertStringContainsString("cause=FailureInfo{message='inner'", $s);
    }

    public function testToStringStackTraceIsTruncated(): void
    {
        $info = new FailureInfo(
            message: 'x',
            stackTrace: \str_repeat('A', 500),
        );
        $s = (string) $info;
        // Should not dump the entire trace — truncated to 120 chars with ellipsis.
        self::assertLessThan(300, \strlen($s));
        self::assertStringContainsString('…', $s);
    }
}
