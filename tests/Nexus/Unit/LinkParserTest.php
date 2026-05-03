<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Link;
use Temporal\Nexus\LinkParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkParser::class)]
#[UsesClass(Link::class)]
#[UsesClass(HandlerException::class)]
#[UsesClass(NexusException::class)]
#[UsesClass(ErrorType::class)]
final class LinkParserTest extends TestCase
{
    public function testFromRawNullReturnsEmpty(): void
    {
        self::assertSame([], LinkParser::fromRaw(null));
    }

    public function testFromRawEmptyArrayReturnsEmpty(): void
    {
        self::assertSame([], LinkParser::fromRaw([]));
    }

    public function testFromRawHappyPath(): void
    {
        $links = LinkParser::fromRaw([
            ['url' => 'https://a/1', 'type' => 'com.example.A'],
            ['url' => 'https://a/2', 'type' => 'com.example.B'],
        ]);

        self::assertCount(2, $links);
        self::assertContainsOnlyInstancesOf(Link::class, $links);
        self::assertSame('https://a/1', $links[0]->uri);
        self::assertSame('com.example.A', $links[0]->type);
        self::assertSame('https://a/2', $links[1]->uri);
        self::assertSame('com.example.B', $links[1]->type);
    }

    public function testFromRawRejectsNonArrayPayload(): void
    {
        try {
            LinkParser::fromRaw('not-an-array');
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::BadRequest, $e->errorType);
            self::assertStringContainsString('must be an array', $e->getMessage());
        }
    }

    public function testFromRawRejectsNonObjectEntry(): void
    {
        try {
            LinkParser::fromRaw([['url' => 'https://a', 'type' => 't'], 'bad']);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::BadRequest, $e->errorType);
            self::assertStringContainsString('index 1 is not an object', $e->getMessage());
        }
    }

    public function testFromRawRejectsMissingUrl(): void
    {
        try {
            LinkParser::fromRaw([['type' => 't']]);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::BadRequest, $e->errorType);
            self::assertStringContainsString('"url"', $e->getMessage());
        }
    }

    public function testFromRawRejectsMissingType(): void
    {
        try {
            LinkParser::fromRaw([['url' => 'https://a']]);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::BadRequest, $e->errorType);
            self::assertStringContainsString('"type"', $e->getMessage());
        }
    }

    public function testFromRawRejectsEmptyUrl(): void
    {
        try {
            LinkParser::fromRaw([['url' => '', 'type' => 't']]);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('"url"', $e->getMessage());
        }
    }

    public function testFromRawRejectsEmptyType(): void
    {
        try {
            LinkParser::fromRaw([['url' => 'https://a', 'type' => '']]);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('"type"', $e->getMessage());
        }
    }

    public function testFromRawStringIndexInError(): void
    {
        try {
            LinkParser::fromRaw(['key1' => 'bad']);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString("index 'key1'", $e->getMessage());
        }
    }

    public function testFromProtoEmpty(): void
    {
        self::assertSame([], LinkParser::fromProto([]));
    }

    public function testFromProtoHappyPath(): void
    {
        $proto = new class {
            public function getUrl(): string { return 'https://p/1'; }
            public function getType(): string { return 'com.example.P'; }
        };

        $links = LinkParser::fromProto([$proto, $proto]);

        self::assertCount(2, $links);
        self::assertSame('https://p/1', $links[0]->uri);
        self::assertSame('com.example.P', $links[0]->type);
    }

    public function testFromProtoRejectsMissingAccessors(): void
    {
        $bare = new \stdClass();

        try {
            LinkParser::fromProto([$bare]);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::BadRequest, $e->errorType);
            self::assertStringContainsString('proto index 0', $e->getMessage());
        }
    }

    public function testFromProtoIndexAdvances(): void
    {
        $good = new class {
            public function getUrl(): string { return 'https://ok'; }
            public function getType(): string { return 't'; }
        };
        $bad = new class {
            public function getUrl(): string { return 'https://ok'; }
            public function getType(): string { return ''; }
        };

        try {
            LinkParser::fromProto([$good, $good, $bad]);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('proto index 2', $e->getMessage());
        }
    }

    public function testFromHeaderSpecExample(): void
    {
        $links = LinkParser::fromHeader('<myscheme://somepath?k=v>; type="com.example.MyResource"');

        self::assertCount(1, $links);
        self::assertSame('myscheme://somepath?k=v', $links[0]->uri);
        self::assertSame('com.example.MyResource', $links[0]->type);
    }

    public function testFromHeaderEmptyReturnsEmpty(): void
    {
        self::assertSame([], LinkParser::fromHeader(''));
        self::assertSame([], LinkParser::fromHeader('   '));
        self::assertSame([], LinkParser::fromHeader([]));
    }

    public function testFromHeaderMultipleEntriesInOneValue(): void
    {
        $links = LinkParser::fromHeader('<https://a/1>; type="t1", <https://a/2>; type="t2"');

        self::assertCount(2, $links);
        self::assertSame('https://a/1', $links[0]->uri);
        self::assertSame('t1', $links[0]->type);
        self::assertSame('https://a/2', $links[1]->uri);
        self::assertSame('t2', $links[1]->type);
    }

    public function testFromHeaderMultipleHeaderValues(): void
    {
        $links = LinkParser::fromHeader([
            '<https://a/1>; type="t1"',
            '<https://a/2>; type="t2"',
        ]);

        self::assertCount(2, $links);
        self::assertSame('t1', $links[0]->type);
        self::assertSame('t2', $links[1]->type);
    }

    public function testFromHeaderToleratesWhitespace(): void
    {
        $links = LinkParser::fromHeader("  <https://a> ; type = \"t\" ,\t<https://b>;type=\"u\"  ");

        self::assertCount(2, $links);
        self::assertSame('https://a', $links[0]->uri);
        self::assertSame('t', $links[0]->type);
        self::assertSame('https://b', $links[1]->uri);
        self::assertSame('u', $links[1]->type);
    }

    public function testFromHeaderQuotedTypeWithCommaAndSemicolon(): void
    {
        // Comma inside the quoted type must NOT split the entry.
        $links = LinkParser::fromHeader('<https://a>; type="weird,type;with-symbols"');

        self::assertCount(1, $links);
        self::assertSame('weird,type;with-symbols', $links[0]->type);
    }

    public function testFromHeaderQuotedTypeWithEscapes(): void
    {
        $links = LinkParser::fromHeader('<https://a>; type="he said \\"hi\\" \\\\"');

        self::assertCount(1, $links);
        self::assertSame('he said "hi" \\', $links[0]->type);
    }

    public function testFromHeaderIgnoresUnrelatedParams(): void
    {
        $links = LinkParser::fromHeader('<https://a>; rel="alternate"; type="t"; title="x"');

        self::assertCount(1, $links);
        self::assertSame('t', $links[0]->type);
    }

    public function testFromHeaderTypeBeforeOtherParams(): void
    {
        $links = LinkParser::fromHeader('<https://a>; type="t"; rel="alternate"');

        self::assertSame('t', $links[0]->type);
    }

    public function testFromHeaderUnquotedTypeToken(): void
    {
        $links = LinkParser::fromHeader('<https://a>; type=token-value');

        self::assertSame('token-value', $links[0]->type);
    }

    public function testFromHeaderRejectsMissingType(): void
    {
        try {
            LinkParser::fromHeader('<https://a>; rel="x"');
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertSame(ErrorType::BadRequest, $e->errorType);
            self::assertStringContainsString('"type"', $e->getMessage());
        }
    }

    public function testFromHeaderRejectsBareUri(): void
    {
        try {
            LinkParser::fromHeader('<https://a>');
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('"type"', $e->getMessage());
        }
    }

    public function testFromHeaderRejectsMissingAngleBrackets(): void
    {
        try {
            LinkParser::fromHeader('https://a; type="t"');
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('"<URI>"', $e->getMessage());
        }
    }

    public function testFromHeaderRejectsMissingClosingBracket(): void
    {
        try {
            LinkParser::fromHeader('<https://a; type="t"');
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('closing ">"', $e->getMessage());
        }
    }

    public function testFromHeaderRejectsEmptyUri(): void
    {
        try {
            LinkParser::fromHeader('<>; type="t"');
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('empty URI', $e->getMessage());
        }
    }

    public function testFromHeaderIndexCountsAcrossValues(): void
    {
        // Second header value, first entry → index 2 overall (0,1 from first value).
        try {
            LinkParser::fromHeader([
                '<https://a>; type="t", <https://b>; type="u"',
                '<https://c>; rel="x"',
            ]);
            self::fail('Expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('header index 2', $e->getMessage());
        }
    }

    public function testFromHeaderRejectsNonStringInIterable(): void
    {
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('Nexus-Link header value must be a string');
        /** @phpstan-ignore-next-line */
        LinkParser::fromHeader([42]);
    }

    public function testFromHeaderRejectsParamWithoutSemicolonSeparator(): void
    {
        // After the URI, anything that doesn't start with `;` aborts type lookup → "missing type"
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('missing required "type"');
        LinkParser::fromHeader('<https://a> type="t"');
    }

    public function testFromHeaderRejectsParamWithoutEquals(): void
    {
        // `; type` without `=value` — parser bails as "missing type"
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('missing required "type"');
        LinkParser::fromHeader('<https://a>; type');
    }

    public function testFromHeaderAcceptsEmptyTypeValueAtEof(): void
    {
        // `; type=` (no value, EOF) — consumeParamValue returns ''. buildLink then rejects as empty type.
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('missing or empty "type"');
        LinkParser::fromHeader('<https://a>; type=');
    }

    public function testFromHeaderRejectsUnterminatedQuotedString(): void
    {
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('unterminated quoted-string');
        LinkParser::fromHeader('<https://a>; type="oops');
    }

    public function testToHeaderEmpty(): void
    {
        self::assertSame('', LinkParser::toHeader([]));
    }

    public function testToHeaderSingle(): void
    {
        $value = LinkParser::toHeader([new Link('https://a', 'com.example.X')]);

        self::assertSame('<https://a>; type="com.example.X"', $value);
    }

    public function testToHeaderMultiple(): void
    {
        $value = LinkParser::toHeader([
            new Link('https://a', 't1'),
            new Link('https://b', 't2'),
        ]);

        self::assertSame('<https://a>; type="t1", <https://b>; type="t2"', $value);
    }

    public function testRoundTripPreservesEverything(): void
    {
        $original = [
            new Link('myscheme://somepath?k=v', 'com.example.MyResource'),
            new Link('https://a', 'with "quotes" and \\ backslash'),
            new Link('https://b', 'comma,inside;type'),
        ];

        $encoded = LinkParser::toHeader($original);
        $parsed = LinkParser::fromHeader($encoded);

        self::assertCount(\count($original), $parsed);
        foreach ($original as $i => $link) {
            self::assertSame($link->uri, $parsed[$i]->uri, "uri #{$i}");
            self::assertSame($link->type, $parsed[$i]->type, "type #{$i}");
        }
    }
}
