<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\LinkParser;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
final class LinkParserTestCase extends AbstractUnit
{
    // ── fromRaw() — absent input ──────────────────────────────────

    public function testFromRawNullReturnsEmpty(): void
    {
        self::assertSame([], LinkParser::fromRaw(null));
    }

    public function testFromRawEmptyArrayReturnsEmpty(): void
    {
        self::assertSame([], LinkParser::fromRaw([]));
    }

    // ── fromRaw() — happy path ────────────────────────────────────

    public function testFromRawParsesWellFormedList(): void
    {
        $links = LinkParser::fromRaw([
            ['url' => 'https://a/', 'type' => 'example.one'],
            ['url' => 'https://b/', 'type' => 'example.two'],
        ]);

        self::assertCount(2, $links);
        self::assertSame('https://a/', $links[0]->uri);
        self::assertSame('example.one', $links[0]->type);
        self::assertSame('https://b/', $links[1]->uri);
        self::assertSame('example.two', $links[1]->type);
    }

    // ── fromRaw() — strict validation ─────────────────────────────

    public function testFromRawNonArrayThrowsBadRequest(): void
    {
        $this->expectHandlerBadRequest('must be an array');
        LinkParser::fromRaw('not-an-array');
    }

    public function testFromRawNonObjectEntryThrows(): void
    {
        $this->expectHandlerBadRequest('is not an object');
        LinkParser::fromRaw([
            ['url' => 'https://ok/', 'type' => 't'],
            'bare-string', // malformed entry
        ]);
    }

    public function testFromRawMissingUrlThrows(): void
    {
        $this->expectHandlerBadRequest('missing or empty "url"');
        LinkParser::fromRaw([
            ['type' => 't'],
        ]);
    }

    public function testFromRawEmptyUrlThrows(): void
    {
        $this->expectHandlerBadRequest('missing or empty "url"');
        LinkParser::fromRaw([
            ['url' => '', 'type' => 't'],
        ]);
    }

    public function testFromRawMissingTypeThrows(): void
    {
        $this->expectHandlerBadRequest('missing or empty "type"');
        LinkParser::fromRaw([
            ['url' => 'https://ok/'],
        ]);
    }

    public function testFromRawNonStringUrlThrows(): void
    {
        // Int url is a type error at the wire layer — surface it.
        $this->expectHandlerBadRequest('missing or empty "url"');
        LinkParser::fromRaw([
            ['url' => 42, 'type' => 't'],
        ]);
    }

    public function testFromRawReportsIndexOfBadEntry(): void
    {
        try {
            LinkParser::fromRaw([
                ['url' => 'https://ok/', 'type' => 't'],
                ['url' => 'https://ok2/', 'type' => ''],
            ]);
            self::fail('expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('index 1', $e->getMessage());
        }
    }

    // ── fromProto() ────────────────────────────────────────────────

    public function testFromProtoParsesValidEntries(): void
    {
        $links = LinkParser::fromProto([
            new FakeProtoLink('https://p1/', 'p.one'),
            new FakeProtoLink('https://p2/', 'p.two'),
        ]);

        self::assertCount(2, $links);
        self::assertSame('https://p1/', $links[0]->uri);
        self::assertSame('p.one', $links[0]->type);
    }

    public function testFromProtoEmptyUrlThrows(): void
    {
        $this->expectHandlerBadRequest('missing or empty "url"');
        LinkParser::fromProto([
            new FakeProtoLink('', 'p.one'),
        ]);
    }

    public function testFromProtoEmptyTypeThrows(): void
    {
        $this->expectHandlerBadRequest('missing or empty "type"');
        LinkParser::fromProto([
            new FakeProtoLink('https://p/', ''),
        ]);
    }

    public function testFromProtoReportsIndexOfBadEntry(): void
    {
        try {
            LinkParser::fromProto([
                new FakeProtoLink('https://ok/', 't'),
                new FakeProtoLink('https://ok2/', ''),
            ]);
            self::fail('expected HandlerException');
        } catch (HandlerException $e) {
            self::assertStringContainsString('proto index 1', $e->getMessage());
        }
    }

    public function testFromProtoObjectWithoutGettersThrowsBadRequest(): void
    {
        // An object that has neither getUrl() nor getType() must not fatal
        // with "call to undefined method"; it should surface as a strict
        // validation error. Regression for an earlier `instanceof stdClass`
        // short-circuit that caused a fatal on stdClass input.
        $this->expectHandlerBadRequest('missing or empty "url"');
        LinkParser::fromProto([new \stdClass()]);
    }

    public function testFromProtoObjectWithPartialGettersIsValidated(): void
    {
        // Only getUrl() defined → type falls back to '' and is rejected
        // by buildLink's non-empty check.
        $this->expectHandlerBadRequest('missing or empty "type"');
        LinkParser::fromProto([new FakeUrlOnly('https://p/')]);
    }

    private function expectHandlerBadRequest(string $messageFragment): void
    {
        $this->expectException(HandlerException::class);
        $this->expectExceptionMessageMatches('/' . \preg_quote($messageFragment, '/') . '/');
    }
}

/**
 * Minimal duck-type for Temporal's proto Link message — `LinkParser::fromProto`
 * only touches `getUrl()` / `getType()` so we avoid pulling in the whole
 * generated stack for unit tests.
 */
final class FakeProtoLink
{
    public function __construct(
        private readonly string $url,
        private readonly string $type,
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

final class FakeUrlOnly
{
    public function __construct(private readonly string $url) {}

    public function getUrl(): string
    {
        return $this->url;
    }
}
