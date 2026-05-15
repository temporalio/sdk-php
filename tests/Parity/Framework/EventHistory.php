<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework;

/**
 * Captured Temporal event-history snapshot tagged with its source SDK.
 *
 * @psalm-immutable
 */
final readonly class EventHistory
{
    /**
     * @param array<int, array<string, mixed>> $events Decoded `events[]` array.
     * @param array<string, mixed> $raw Full decoded JSON (includes `events` and any sibling keys).
     */
    public function __construct(
        public Source $source,
        public array $events,
        public array $raw,
    ) {}
}
