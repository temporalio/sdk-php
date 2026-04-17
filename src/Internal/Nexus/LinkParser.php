<?php

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Nexus\Sdk\Exception\ErrorType as NexusErrorType;
use Nexus\Sdk\Exception\HandlerException as NexusHandlerException;
use Nexus\Sdk\Link;

/**
 * Parser for caller-side Nexus links received over the RR transport.
 *
 * Two shapes converge into the same {@see Link} value object:
 *
 *  - **Raw** — JSON-decoded list of `{url: string, type: string}` objects,
 *    delivered as the `options.links` field on the `InvokeNexusOperation`
 *    route.
 *  - **Proto** — Temporal's `Temporal\Api\Nexus\V1\Link` messages, delivered
 *    on the proto-path `StartOperationRequest::getLinks()`.
 *
 * ## Policy
 *
 * **Strict** — any malformed entry raises a `HandlerException{BadRequest}`
 * so the caller receives HTTP 400. Matches the Java reference SDK and
 * guards against silent data loss / client-side bugs going unnoticed.
 *
 * Absent input (missing key, `null`, or empty iterable) resolves to `[]`
 * rather than an error: "no links" is a legitimate state, "bad links
 * payload" is not.
 */
final class LinkParser
{
    /**
     * Parse a JSON-decoded list of `{url, type}` objects into `Link[]`.
     *
     * @return Link[]
     *
     * @throws NexusHandlerException when the payload or any entry is malformed
     */
    public static function fromRaw(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (!\is_array($raw)) {
            throw NexusHandlerException::create(
                NexusErrorType::BadRequest,
                \sprintf('Nexus links must be an array, got %s', \get_debug_type($raw)),
            );
        }

        $links = [];
        foreach ($raw as $index => $entry) {
            if (!\is_array($entry)) {
                throw NexusHandlerException::create(
                    NexusErrorType::BadRequest,
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
     * Parse Temporal proto `Link` messages into the SDK `Link[]`.
     *
     * @param iterable<object> $protoLinks Objects exposing `getUrl()` / `getType()` (proto-generated or stub).
     * @return Link[]
     *
     * @throws NexusHandlerException on any malformed entry
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
     * Validate and construct a single {@see Link}, throwing a
     * HandlerException with the specific offending field highlighted.
     */
    private static function buildLink(mixed $url, mixed $type, string $context): Link
    {
        if (!\is_string($url) || $url === '') {
            throw NexusHandlerException::create(
                NexusErrorType::BadRequest,
                \sprintf('Nexus link at %s has missing or empty "url"', $context),
            );
        }
        if (!\is_string($type) || $type === '') {
            throw NexusHandlerException::create(
                NexusErrorType::BadRequest,
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
