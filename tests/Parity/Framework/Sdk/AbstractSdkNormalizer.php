<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Sdk;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\EventNormalizerInterface;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

/**
 * Walks an event tree and dispatches each known leaf-key name to the matching
 * field normalizer. Routing is by leaf-key only — same key carries the same
 * value shape wherever it appears.
 */
abstract class AbstractSdkNormalizer implements EventNormalizerInterface
{
    /**
     * @param array<string, FieldNormalizerInterface> $sharedFieldRules base key→normalizer map
     *        (timestamps, IDs, identities, …). Constructed once in the registry.
     */
    public function __construct(
        protected readonly array $sharedFieldRules,
        protected readonly ?LoggerInterface $logger = null,
    ) {}

    abstract protected function source(): Source;

    /**
     * @return array<string, FieldNormalizerInterface> SDK-specific rules merged on top of the shared map.
     */
    protected function additionalFieldRules(): array
    {
        return [];
    }

    /**
     * @return list<string> Top-level event keys to drop entirely (per SDK).
     *        Useful for fields that exist on one SDK and not the other.
     */
    protected function dropKeys(): array
    {
        return [];
    }

    /**
     * @return list<string> Whole event types to drop (per SDK). Use only for
     *        events that one SDK records and the other does not for the same
     *        business outcome (e.g. Go SDK emits a TIMER_CANCELED before
     *        WORKFLOW_EXECUTION_COMPLETED when an `awaitWithTimeout` is
     *        resolved by the predicate; Java SDK omits it).
     */
    protected function dropEventTypes(): array
    {
        return [];
    }

    /**
     * @return list<string> Keys to drop wherever they appear in the event tree.
     *        Use for fields whose presence/shape diverges between SDKs in a way
     *        that can't be smoothed by a field normalizer (e.g. one SDK emits
     *        an empty map, another emits a non-empty map, a third omits the
     *        key entirely).
     */
    protected function dropAnywhereKeys(): array
    {
        return [
            'header',
            'priority',
            'workerVersion',
            'meteringMetadata',
            'useWorkflowBuildId',
            'inheritBuildId',
            'workflowIdReusePolicy',
            'source',
            'applicationFailureInfo',
            'side-effect-id',
            'side_effect_id',
            'sdkMetadata',
            'cause',
            'data',
            'stackTrace',
            'userMetadata',
            'workflowExecutionExpirationTime',
            // signal/child-workflow attrs that one SDK emits and another omits:
            'runId',
            'namespace',
            'namespaceId',
            'control',
            'childWorkflowOnly',
        ];
    }

    public function normalize(array $event): array
    {
        $rules = $this->fieldRules();
        $drop = $this->dropKeys();

        if (\in_array(($event['eventType'] ?? null), $this->dropEventTypes(), true)) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$this->source()->value} dropped event type \"{$event['eventType']}\"");
            return [];
        }

        foreach ($drop as $key) {
            if (\array_key_exists($key, $event)) {
                $this->logger?->log(LogLevel::DEBUG, "parity: {$this->source()->value} dropped top-level key \"{$key}\"");
                unset($event[$key]);
            }
        }

        $event = $this->collapseLocalActivityMarker($event);
        $event = $this->collapseVersionMarker($event);
        $event = $this->collapseNullSignalInput($event);

        return $this->walk($event, $rules);
    }

    /**
     * Go SDK sends `c.SignalWorkflow(..., nil)` as a single payload with
     * `encoding: binary/null`; PHP omits `input` entirely. Drop the key from
     * `workflowExecutionSignaledEventAttributes` when all payloads encode null.
     */
    private function collapseNullSignalInput(array $event): array
    {
        if (($event['eventType'] ?? null) !== 'EVENT_TYPE_WORKFLOW_EXECUTION_SIGNALED') {
            return $event;
        }
        $payloads = $event['workflowExecutionSignaledEventAttributes']['input']['payloads'] ?? null;
        if (!\is_array($payloads) || $payloads === []) {
            return $event;
        }
        foreach ($payloads as $payload) {
            $encoding = $payload['metadata']['encoding'] ?? null;
            $decoded = \is_string($encoding) ? \base64_decode($encoding, true) : null;
            if ($decoded !== 'binary/null') {
                return $event;
            }
        }
        unset($event['workflowExecutionSignaledEventAttributes']['input']);
        $this->logger?->log(LogLevel::DEBUG, "parity: {$this->source()->value} dropped null-only signal input");
        return $event;
    }

    /**
     * Java records LocalActivity bookkeeping as granular fields
     * (`activityId`, `input`, `meta`, `time`, `type`) while Go-based SDKs (RR/PHP)
     * record it as one opaque `data` blob (already dropped via `dropAnywhereKeys`).
     * Both carry the same replay state; parity only asserts the activity outcome,
     * so reduce both sides to just `result`.
     */
    private function collapseLocalActivityMarker(array $event): array
    {
        if (($event['eventType'] ?? null) !== 'EVENT_TYPE_MARKER_RECORDED') {
            return $event;
        }
        if (($event['markerRecordedEventAttributes']['markerName'] ?? null) !== 'LocalActivity') {
            return $event;
        }

        $details = $event['markerRecordedEventAttributes']['details'] ?? [];
        $event['markerRecordedEventAttributes']['details'] = isset($details['result'])
            ? ['result' => $details['result']]
            : [];

        $this->logger?->log(LogLevel::DEBUG, "parity: {$this->source()->value} collapsed LocalActivity marker details to {result}");

        return $event;
    }

    /**
     * Java records `Workflow.getVersion()` as `{changeId, version}` in marker details
     * while Go-based SDKs (RR/PHP) record it as `{change-id, version}`. The change-id
     * (kebab vs camelCase) is purely a serialization quirk; keep only `version`,
     * which is the value the workflow code observes.
     */
    private function collapseVersionMarker(array $event): array
    {
        if (($event['eventType'] ?? null) !== 'EVENT_TYPE_MARKER_RECORDED') {
            return $event;
        }
        if (($event['markerRecordedEventAttributes']['markerName'] ?? null) !== 'Version') {
            return $event;
        }

        $details = $event['markerRecordedEventAttributes']['details'] ?? [];
        $event['markerRecordedEventAttributes']['details'] = isset($details['version'])
            ? ['version' => $details['version']]
            : [];

        $this->logger?->log(LogLevel::DEBUG, "parity: {$this->source()->value} collapsed Version marker details to {version}");

        return $event;
    }

    /**
     * @param array<string, FieldNormalizerInterface> $rules
     * @return array<string, FieldNormalizerInterface>
     */
    private function fieldRules(): array
    {
        return [...$this->sharedFieldRules, ...$this->additionalFieldRules()];
    }

    /**
     * @param array<string, FieldNormalizerInterface> $rules
     */
    private function walk(mixed $node, array $rules): mixed
    {
        if (!\is_array($node)) {
            return $node;
        }

        $dropAnywhere = \array_flip($this->dropAnywhereKeys());
        $result = [];
        foreach ($node as $key => $value) {
            if (\is_string($key) && isset($dropAnywhere[$key])) {
                $this->logger?->log(LogLevel::DEBUG, "parity: {$this->source()->value} dropped nested key \"{$key}\"");
                continue;
            }
            if (\is_array($value) && $value === []) {
                continue;
            }
            if (\is_string($key) && isset($rules[$key])) {
                $result[$key] = $rules[$key]->normalize($value, $this->source());
                continue;
            }
            $result[$key] = $this->walk($value, $rules);
        }
        return $result;
    }
}
