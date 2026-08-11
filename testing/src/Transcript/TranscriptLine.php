<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

final class TranscriptLine
{
    /**
     * @param array<string, scalar|null> $attributes
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        public readonly \DateTimeImmutable $timestamp,
        public readonly int $processId,
        public readonly int $sequence,
        public readonly TranscriptSection $section,
        public readonly array $attributes,
        public readonly ?array $payload,
        public readonly string $rawLine,
    ) {}

    public function getAttribute(string $key): string|int|float|bool|null
    {
        return $this->attributes[$key] ?? null;
    }

    public function hasAttribute(string $key): bool
    {
        return \array_key_exists($key, $this->attributes);
    }
}
