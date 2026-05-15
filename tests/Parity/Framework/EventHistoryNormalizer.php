<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework;

/**
 * Picks an SDK-specific event normalizer from a `Source`-keyed map and
 * applies it to every event in an `EventHistory`. The returned list is the
 * comparable form — feed it into `assertEquals` against another normalized
 * history.
 */
final class EventHistoryNormalizer
{
    /**
     * @param array<string, EventNormalizerInterface> $normalizers keyed by `Source->value`
     */
    public function __construct(
        private readonly array $normalizers,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function normalize(EventHistory $history): array
    {
        $normalizer = $this->normalizers[$history->source->value]
            ?? throw new \RuntimeException(
                "No event normalizer registered for source \"{$history->source->value}\"",
            );

        return \array_values(\array_map(
            static fn (array $event): array => $normalizer->normalize($event),
            $history->events,
        ));
    }
}
