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
        ];
    }

    public function normalize(array $event): array
    {
        $rules = $this->fieldRules();
        $drop = $this->dropKeys();

        foreach ($drop as $key) {
            if (\array_key_exists($key, $event)) {
                $this->logger?->log(LogLevel::DEBUG, "parity: {$this->source()->value} dropped top-level key \"{$key}\"");
                unset($event[$key]);
            }
        }

        return $this->walk($event, $rules);
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
