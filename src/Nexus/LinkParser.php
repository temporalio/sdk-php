<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;

/**
 * Parses Nexus links from raw JSON / proto / `Nexus-Link` header into `Link[]`.
 * Strict: malformed input throws {@see HandlerException} (BadRequest).
 * Absent input returns `[]`.
 */
final class LinkParser
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @return Link[]
     * @throws HandlerException
     */
    public static function fromRaw(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (!\is_array($raw)) {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf('Nexus links must be an array, got %s', \get_debug_type($raw)),
            );
        }

        $links = [];
        foreach ($raw as $index => $entry) {
            if (!\is_array($entry)) {
                throw HandlerException::create(
                    ErrorType::BadRequest,
                    \sprintf(
                        'Nexus link at index %s is not an object (got %s)',
                        self::formatIndex($index),
                        \get_debug_type($entry),
                    ),
                );
            }
            $links[] = self::buildLink(
                url: $entry['url'] ?? null,
                type: $entry['type'] ?? null,
                context: 'index ' . self::formatIndex($index),
            );
        }
        return $links;
    }

    /**
     * @param iterable<object> $protoLinks Objects exposing `getUrl()` / `getType()`.
     * @return Link[]
     * @throws HandlerException
     */
    public static function fromProto(iterable $protoLinks): array
    {
        $links = [];
        $index = 0;
        foreach ($protoLinks as $protoLink) {
            $url = \method_exists($protoLink, 'getUrl') ? (string) $protoLink->getUrl() : '';
            $type = \method_exists($protoLink, 'getType') ? (string) $protoLink->getType() : '';
            $links[] = self::buildLink(
                url: $url,
                type: $type,
                context: 'proto index ' . $index,
            );
            $index++;
        }
        return $links;
    }

    /**
     * Parse `Nexus-Link` header value(s) per RFC 8288. Required parameter:
     * `type`; other parameters tolerated. Quoted-string supports `\\` / `\"`.
     *
     * @param string|iterable<string> $value Single value or repeated headers.
     * @return Link[]
     * @throws HandlerException
     */
    public static function fromHeader(string|iterable $value): array
    {
        $values = \is_string($value) ? [$value] : $value;

        $links = [];
        $index = 0;
        foreach ($values as $headerValue) {
            if (!\is_string($headerValue)) {
                throw HandlerException::create(
                    ErrorType::BadRequest,
                    \sprintf('Nexus-Link header value must be a string, got %s', \get_debug_type($headerValue)),
                );
            }
            if (\trim($headerValue) === '') {
                continue;
            }
            foreach (self::splitHeaderEntries($headerValue) as $entry) {
                $links[] = self::parseHeaderEntry($entry, $index);
                $index++;
            }
        }
        return $links;
    }

    /**
     * Encode `Link[]` as a single `Nexus-Link` header value. Empty list → empty string.
     *
     * @param Link[] $links
     */
    public static function toHeader(array $links): string
    {
        return \implode(', ', \array_map(static fn(Link $l): string => $l->toHeaderValue(), $links));
    }

    /**
     * Split on top-level commas (outside `<...>` and `"..."`).
     *
     * @return list<string>
     */
    private static function splitHeaderEntries(string $value): array
    {
        $entries = [];
        $current = '';
        $inAngle = false;
        $inQuote = false;

        for ($i = 0, $n = \strlen($value); $i < $n; $i++) {
            $c = $value[$i];

            if ($inQuote) {
                $current .= $c;
                if ($c === '\\' && $i + 1 < $n) {
                    $current .= $value[++$i];
                } elseif ($c === '"') {
                    $inQuote = false;
                }
                continue;
            }
            if ($inAngle) {
                $current .= $c;
                if ($c === '>') {
                    $inAngle = false;
                }
                continue;
            }
            if ($c === '<') {
                $inAngle = true;
                $current .= $c;
                continue;
            }
            if ($c === '"') {
                $inQuote = true;
                $current .= $c;
                continue;
            }
            if ($c === ',') {
                if (\trim($current) !== '') {
                    $entries[] = $current;
                }
                $current = '';
                continue;
            }
            $current .= $c;
        }
        if (\trim($current) !== '') {
            $entries[] = $current;
        }
        return $entries;
    }

    private static function parseHeaderEntry(string $entry, int $index): Link
    {
        $entry = \trim($entry);
        $context = "header index {$index}";

        if ($entry === '' || $entry[0] !== '<') {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf('Nexus-Link entry at %s must start with "<URI>"', $context),
            );
        }
        $close = \strpos($entry, '>');
        if ($close === false) {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf('Nexus-Link entry at %s is missing the closing ">"', $context),
            );
        }
        $uri = \trim(\substr($entry, 1, $close - 1));
        if ($uri === '') {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf('Nexus-Link entry at %s has an empty URI', $context),
            );
        }

        $type = self::extractTypeParam(\substr($entry, $close + 1));
        if ($type === null) {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf('Nexus-Link entry at %s is missing required "type" parameter', $context),
            );
        }

        return self::buildLink(url: $uri, type: $type, context: $context);
    }

    private static function extractTypeParam(string $rest): ?string
    {
        $rest = \ltrim($rest);
        while ($rest !== '') {
            if ($rest[0] !== ';') {
                return null;
            }
            $rest = \ltrim(\substr($rest, 1));
            if (!\preg_match('/^([!#$%&\'*+\-.^_`|~0-9A-Za-z]+)\s*=\s*/', $rest, $m)) {
                return null;
            }
            $key = \strtolower($m[1]);
            $rest = \substr($rest, \strlen($m[0]));

            [$value, $rest] = self::consumeParamValue($rest);
            if ($key === 'type') {
                return $value;
            }
        }
        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function consumeParamValue(string $rest): array
    {
        if ($rest === '') {
            return ['', ''];
        }
        if ($rest[0] === '"') {
            $value = '';
            $i = 1;
            $n = \strlen($rest);
            while ($i < $n && $rest[$i] !== '"') {
                if ($rest[$i] === '\\' && $i + 1 < $n) {
                    $value .= $rest[$i + 1];
                    $i += 2;
                } else {
                    $value .= $rest[$i];
                    $i++;
                }
            }
            if ($i >= $n) {
                throw HandlerException::create(
                    ErrorType::BadRequest,
                    'Nexus-Link parameter has unterminated quoted-string',
                );
            }
            return [$value, \ltrim(\substr($rest, $i + 1))];
        }
        \preg_match('/^([!#$%&\'*+\-.^_`|~0-9A-Za-z]*)/', $rest, $m);
        $value = $m[1];
        return [$value, \ltrim(\substr($rest, \strlen($value)))];
    }

    private static function buildLink(mixed $url, mixed $type, string $context): Link
    {
        if (!\is_string($url) || $url === '') {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf('Nexus link at %s has missing or empty "url"', $context),
            );
        }
        if (!\is_string($type) || $type === '') {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf('Nexus link at %s has missing or empty "type"', $context),
            );
        }
        return new Link($url, $type);
    }

    private static function formatIndex(mixed $index): string
    {
        return \is_int($index) ? (string) $index : \var_export($index, true);
    }
}
