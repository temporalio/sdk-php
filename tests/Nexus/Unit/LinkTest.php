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
        // The plain constructor is intentionally permissive — only non-emptiness
        // is enforced. Use fromUri() for strict absolute-URI validation.
        $link = new Link('/just-a-path', 'some-type');
        self::assertSame('/just-a-path', $link->uri);
    }

    // ── fromUri() strict factory ────────────────────────────────────

    public function testFromUriAcceptsAbsoluteUrl(): void
    {
        $link = Link::fromUri('https://example.com/x', 'example');
        self::assertSame('https://example.com/x', $link->uri);
        self::assertSame('example', $link->type);
    }

    public function testFromUriAcceptsUrnScheme(): void
    {
        // Nexus links are opaque URI references; schemes other than http(s)
        // must be accepted. The Java reference impl uses java.net.URI which
        // treats URNs as valid.
        $link = Link::fromUri('urn:temporal:workflow:abc', 'temporal.workflow');
        self::assertSame('urn:temporal:workflow:abc', $link->uri);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function nonAbsoluteUriProvider(): iterable
    {
        yield 'relative path'        => ['/just-a-path'];
        yield 'bare name'            => ['example.com'];
        yield 'empty scheme'         => ['://example.com'];
        yield 'fragment only'        => ['#section'];
    }

    #[DataProvider('nonAbsoluteUriProvider')]
    public function testFromUriRejectsNonAbsolute(string $uri): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be absolute with a scheme/');
        Link::fromUri($uri, 'example');
    }

    public function testFromUriRejectsEmpty(): void
    {
        // fromUri delegates to the constructor, so the empty check still fires
        // — but from parse_url which yields no scheme.
        $this->expectException(InvalidArgumentException::class);
        Link::fromUri('', 'example');
    }

    public function testFromUriStillEnforcesNonEmptyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link type must not be empty');
        Link::fromUri('https://example.com', '');
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
