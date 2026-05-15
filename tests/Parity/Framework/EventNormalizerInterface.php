<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework;

/**
 * SDK-specific strategy that turns a single decoded event (from a Temporal
 * `temporal workflow show --output json` dump) into its normalized form,
 * suitable for cross-SDK `assertEquals` comparison.
 */
interface EventNormalizerInterface
{
    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function normalize(array $event): array;
}
