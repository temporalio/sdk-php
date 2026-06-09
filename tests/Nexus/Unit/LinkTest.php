<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Link;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Link::class)]
final class LinkTest extends TestCase
{
    public function testConstruction(): void
    {
        $link = new Link('http://example.com?k=v', 'com.example.MyResource');
        self::assertSame('http://example.com?k=v', $link->uri);
        self::assertSame('com.example.MyResource', $link->type);
    }

    public function testToString(): void
    {
        $link = new Link('http://example.com', 'MyType');
        $str = (string) $link;
        self::assertStringContainsString('http://example.com', $str);
        self::assertStringContainsString('MyType', $str);
    }

    public function testEquality(): void
    {
        $link1 = new Link('http://a.com', 'TypeA');
        $link2 = new Link('http://a.com', 'TypeA');
        $link3 = new Link('http://b.com', 'TypeB');

        self::assertEquals($link1, $link2);
        self::assertNotEquals($link1, $link3);
    }

    // ── Constructor validation ──────────────────────────────────────

    public function testEmptyUriRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link URI must not be empty');
        new Link('', 'some-type');
    }

    public function testEmptyTypeRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link type must not be empty');
        new Link('http://example.com', '');
    }

    public function testConstructorAcceptsRelativeUri(): void
    {
        // The plain constructor is intentionally permissive — only non-emptiness is enforced.
        $link = new Link('/just-a-path', 'some-type');
        self::assertSame('/just-a-path', $link->uri);
    }

    public function testToHeaderValueWrapsUriAndQuotesType(): void
    {
        $link = new Link('https://x/y', 'app/foo');
        self::assertSame('<https://x/y>; type="app/foo"', $link->toHeaderValue());
    }

    public function testToHeaderValueEscapesQuoteAndBackslashInType(): void
    {
        $link = new Link('https://x', 'a"b\\c');
        self::assertSame('<https://x>; type="a\\"b\\\\c"', $link->toHeaderValue());
    }
}
