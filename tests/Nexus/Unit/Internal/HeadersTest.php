<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Internal;

use Temporal\Nexus\Internal\Headers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Headers::class)]
final class HeadersTest extends TestCase
{
    public function testNormalizeLowercasesKeys(): void
    {
        self::assertSame(
            ['foo' => 'bar', 'baz' => 'qux'],
            Headers::normalize(['FOO' => 'bar', 'Baz' => 'qux']),
        );
    }

    public function testNormalizePreservesValueCase(): void
    {
        self::assertSame(
            ['content-type' => 'Application/JSON'],
            Headers::normalize(['Content-Type' => 'Application/JSON']),
        );
    }

    public function testNormalizeEmpty(): void
    {
        self::assertSame([], Headers::normalize([]));
    }

    public function testNormalizeLastValueWinsOnCaseCollision(): void
    {
        // Same key with different casing — last one wins after normalization.
        self::assertSame(
            ['x-custom' => 'second'],
            Headers::normalize(['X-Custom' => 'first', 'x-custom' => 'second']),
        );
    }
}
