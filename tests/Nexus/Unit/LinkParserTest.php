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
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('Nexus link at index 0 has missing or empty "url"');
        LinkParser::fromRaw([['url' => '', 'type' => 't']]);
    }

    public function testFromRawRejectsEmptyType(): void
    {
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('Nexus link at index 0 has missing or empty "type"');
        LinkParser::fromRaw([['url' => 'https://a', 'type' => '']]);
    }

    public function testFromRawStringIndexInError(): void
    {
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage("Nexus link at index 'key1' is not an object (got string)");
        LinkParser::fromRaw(['key1' => 'bad']);
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

        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('Nexus link at proto index 2 has missing or empty "type"');
        LinkParser::fromProto([$good, $good, $bad]);
    }
}
