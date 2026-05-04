<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Temporal\Api\Common\V1\Link;
use Temporal\Api\Common\V1\Link\WorkflowEvent;
use Temporal\Api\Common\V1\Link\WorkflowEvent\EventReference;
use Temporal\Api\Common\V1\Link\WorkflowEvent\RequestIdReference;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Link as NexusLink;

/**
 * URI ↔ proto Link.WorkflowEvent converter, mirror of Go SDK
 * `temporalnexus/link_converter.go`. Handles only the WorkflowEvent variant —
 * other Nexus-Link types (custom user types) are silently skipped per Go SDK
 * semantics in `convertNexusLinks`.
 *
 * Wire format: temporal:///namespaces/{ns}/workflows/{wf}/{run}/history?referenceType=…&…
 *
 * @internal
 */
final class NexusLinkConverter
{
    private const SCHEME = 'temporal';
    private const PATH_REGEX = '#^/namespaces/([^/]+)/workflows/([^/]+)/([^/]+)/history$#';
    public const TYPE_WORKFLOW_EVENT = 'temporal.api.common.v1.Link.WorkflowEvent';
    private const REF_TYPE_EVENT = 'EventReference';
    private const REF_TYPE_REQUEST_ID = 'RequestIdReference';
    private const QUERY_REFERENCE_TYPE = 'referenceType';
    private const QUERY_EVENT_ID       = 'eventID';
    private const QUERY_EVENT_TYPE     = 'eventType';
    private const QUERY_REQUEST_ID     = 'requestID';

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Convert a list of high-level Nexus links to proto Link[] suitable for
     * StartWorkflowExecutionRequest.links / Callback.links.
     *
     * Policy (matches TypeScript and Go SDKs):
     * - Non-`temporal.api.common.v1.Link.WorkflowEvent` types — silently dropped
     *   (a custom user-defined link type is not an error, just not supported here).
     * - Malformed WorkflowEvent URIs — throw InvalidArgumentException. Java and
     *   Python SDKs log+drop instead; we follow Go/TS because we have no logger
     *   wired into this layer and a malformed link is a wire-level bug worth
     *   surfacing.
     *
     * @param iterable<NexusLink> $links
     * @return list<Link>
     * @throws InvalidArgumentException on malformed WorkflowEvent URI.
     */
    public static function toProtoLinks(iterable $links): array
    {
        $out = [];
        foreach ($links as $link) {
            if ($link->type !== self::TYPE_WORKFLOW_EVENT) {
                continue;
            }
            $out[] = self::convertOne($link);
        }
        return $out;
    }

    /**
     * Encode a `Link.WorkflowEvent` into a Nexus URI link. Mirrors:
     * - Java  `LinkConverter.workflowEventToNexusLink`
     * - Go    `ConvertLinkWorkflowEventToNexusLink`
     * - TS    `convertWorkflowEventLinkToNexusLink`
     * - Py    `workflow_event_to_nexus_link`
     *
     * Wire `eventType` is written in PascalCase form (`WorkflowExecutionStarted`)
     * to match Java/TypeScript/Python and modern Temporal server. Decoder
     * accepts both PascalCase and legacy `EVENT_TYPE_*`.
     *
     * @throws InvalidArgumentException when WorkflowEvent has neither
     *         event_ref nor request_id_ref set, or carries an unknown
     *         EventType enum value.
     */
    public static function workflowEventToNexusLink(WorkflowEvent $event): NexusLink
    {
        $path = \sprintf(
            '/namespaces/%s/workflows/%s/%s/history',
            \rawurlencode($event->getNamespace()),
            \rawurlencode($event->getWorkflowId()),
            \rawurlencode($event->getRunId()),
        );

        $query = self::buildEventQuery($event);
        $uri = 'temporal://' . $path . '?' . self::encodeQuery($query);

        return new NexusLink($uri, self::TYPE_WORKFLOW_EVENT);
    }

    private static function convertOne(NexusLink $link): Link
    {
        // Manual scheme/path/query split — PHP's parse_url rejects `scheme:///path` URIs
        // (empty authority) used by the Temporal Nexus link format.
        if (!\preg_match('~^([a-zA-Z][a-zA-Z0-9+.\-]*):(?://[^/?\#]*)?(/[^?\#]*)(?:\?([^\#]*))?(?:\#.*)?$~', $link->uri, $u)) {
            throw new InvalidArgumentException(\sprintf(
                'malformed Nexus link URI: "%s"',
                $link->uri,
            ));
        }
        $scheme = $u[1];
        $path = $u[2];
        $queryString = $u[3] ?? '';

        if ($scheme !== self::SCHEME) {
            throw new InvalidArgumentException(\sprintf(
                'Nexus link URI scheme must be "temporal", got "%s" in "%s"',
                $scheme,
                $link->uri,
            ));
        }
        if (!\preg_match(self::PATH_REGEX, $path, $m)) {
            throw new InvalidArgumentException(\sprintf(
                'Nexus link URI path does not match /namespaces/{ns}/workflows/{wf}/{run}/history: "%s"',
                $path,
            ));
        }
        $namespace = \rawurldecode($m[1]);
        $workflowId = \rawurldecode($m[2]);
        $runId = \rawurldecode($m[3]);

        $query = [];
        if ($queryString !== '') {
            \parse_str($queryString, $query);
        }

        $event = (new WorkflowEvent())
            ->setNamespace($namespace)
            ->setWorkflowId($workflowId)
            ->setRunId($runId);

        $referenceType = (string) ($query[self::QUERY_REFERENCE_TYPE] ?? '');
        $eventTypeName = (string) ($query[self::QUERY_EVENT_TYPE] ?? '');
        $eventTypeValue = self::resolveEventType($eventTypeName);
        if ($eventTypeValue === null) {
            throw new InvalidArgumentException(\sprintf(
                'unknown EventType "%s" in Nexus link URI "%s"',
                $eventTypeName,
                $link->uri,
            ));
        }

        match ($referenceType) {
            self::REF_TYPE_EVENT => $event->setEventRef(self::buildEventRef($query, $eventTypeValue, $link->uri)),
            self::REF_TYPE_REQUEST_ID => $event->setRequestIdRef(self::buildRequestIdRef($query, $eventTypeValue)),
            default => throw new InvalidArgumentException(\sprintf(
                'unknown referenceType "%s" in Nexus link URI "%s"',
                $referenceType,
                $link->uri,
            )),
        };

        $proto = new Link();
        $proto->setWorkflowEvent($event);
        return $proto;
    }

    /**
     * Resolve a wire `eventType` value to a protobuf int. Accepts both forms:
     * - "EVENT_TYPE_WORKFLOW_EXECUTION_STARTED" (legacy / Go SDK style)
     * - "WorkflowExecutionStarted" (modern PascalCase, Java/TS/Python style)
     */
    private static function resolveEventType(string $name): ?int
    {
        if ($name === '') {
            return null;
        }

        if (\str_starts_with($name, 'EVENT_TYPE_')) {
            return self::lookupEventTypeValue($name);
        }

        if (!\preg_match('/^[A-Z]/', $name)) {
            return null;
        }

        return self::lookupEventTypeValue('EVENT_TYPE_' . self::pascalCaseToConstantCase($name));
    }

    private static function lookupEventTypeValue(string $screamingName): ?int
    {
        try {
            return EventType::value($screamingName);
        } catch (\UnexpectedValueException) {
            return null;
        }
    }

    /**
     * "WorkflowExecutionStarted" → "WORKFLOW_EXECUTION_STARTED".
     */
    private static function pascalCaseToConstantCase(string $pascal): string
    {
        if ($pascal === '') {
            return '';
        }
        $withUnderscores = \preg_replace('/(?<!^)([A-Z])/', '_$1', $pascal);
        return \strtoupper($withUnderscores ?? $pascal);
    }

    /**
     * @param array<string,mixed> $query
     */
    private static function buildEventRef(array $query, int $eventType, string $uri): EventReference
    {
        $ref = new EventReference();
        $ref->setEventType($eventType);
        if (isset($query[self::QUERY_EVENT_ID]) && $query[self::QUERY_EVENT_ID] !== '') {
            if (!\preg_match('/^\\d+$/', (string) $query[self::QUERY_EVENT_ID])) {
                throw new InvalidArgumentException(\sprintf(
                    'eventID is not an integer in Nexus link URI "%s"',
                    $uri,
                ));
            }
            $ref->setEventId((int) $query[self::QUERY_EVENT_ID]);
        }
        return $ref;
    }

    /**
     * @param array<string,mixed> $query
     */
    private static function buildRequestIdRef(array $query, int $eventType): RequestIdReference
    {
        $requestId = $query[self::QUERY_REQUEST_ID] ?? '';
        if (!\is_string($requestId)) {
            $requestId = '';
        }
        $ref = new RequestIdReference();
        $ref->setRequestId($requestId);
        $ref->setEventType($eventType);
        return $ref;
    }

    /**
     * @return array<string, string>
     */
    private static function buildEventQuery(WorkflowEvent $event): array
    {
        if ($event->hasEventRef()) {
            $eventRef = $event->getEventRef();
            \assert($eventRef !== null);
            $query = [self::QUERY_REFERENCE_TYPE => self::REF_TYPE_EVENT];
            $eventId = (int) $eventRef->getEventId();
            if ($eventId > 0) {
                $query[self::QUERY_EVENT_ID] = (string) $eventId;
            }
            $query[self::QUERY_EVENT_TYPE] = self::encodeEventTypeName($eventRef->getEventType(), $event);
            return $query;
        }
        if ($event->hasRequestIdRef()) {
            $requestRef = $event->getRequestIdRef();
            \assert($requestRef !== null);
            $query = [self::QUERY_REFERENCE_TYPE => self::REF_TYPE_REQUEST_ID];
            $requestId = $requestRef->getRequestId();
            if ($requestId !== '') {
                $query[self::QUERY_REQUEST_ID] = $requestId;
            }
            $query[self::QUERY_EVENT_TYPE] = self::encodeEventTypeName($requestRef->getEventType(), $event);
            return $query;
        }
        throw new InvalidArgumentException(
            'WorkflowEvent must have either event_ref or request_id_ref set',
        );
    }

    /**
     * @param array<string, string> $query
     */
    private static function encodeQuery(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = \rawurlencode($key) . '=' . \rawurlencode($value);
        }
        return \implode('&', $parts);
    }

    /**
     * Convert protobuf int → PascalCase wire form (`WorkflowExecutionStarted`).
     *
     * @throws InvalidArgumentException when $value is not a known EventType.
     */
    private static function encodeEventTypeName(int $value, WorkflowEvent $event): string
    {
        try {
            $screaming = EventType::name($value);
        } catch (\UnexpectedValueException) {
            $screaming = null;
        }
        if ($screaming === null) {
            throw new InvalidArgumentException(\sprintf(
                'unknown EventType enum value %d in WorkflowEvent (workflow_id="%s")',
                $value,
                $event->getWorkflowId(),
            ));
        }
        if (!\str_starts_with($screaming, 'EVENT_TYPE_')) {
            return $screaming;
        }
        return self::constantCaseToPascalCase(\substr($screaming, \strlen('EVENT_TYPE_')));
    }

    /**
     * "WORKFLOW_EXECUTION_STARTED" → "WorkflowExecutionStarted".
     */
    private static function constantCaseToPascalCase(string $screaming): string
    {
        if ($screaming === '') {
            return '';
        }
        $segments = \explode('_', \strtolower($screaming));
        return \implode('', \array_map(\ucfirst(...), $segments));
    }
}
