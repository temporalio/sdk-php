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
 * Parses Nexus links from raw JSON / proto into `Link[]`.
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
