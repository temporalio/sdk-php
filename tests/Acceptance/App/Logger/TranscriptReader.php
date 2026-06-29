<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

final class TranscriptReader
{
    /** @var list<string> */
    private array $files;

    public function __construct(string $directory)
    {
        $matches = \glob($directory . '/*.log');
        $this->files = \is_array($matches) ? $matches : [];
    }

    /**
     * @return list<TranscriptLine>
     */
    public function getLines(): array
    {
        $lines = [];
        foreach ($this->files as $file) {
            $lineNumber = 0;
            $handle = @\fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }
            try {
                while (($raw = \fgets($handle)) !== false) {
                    $lineNumber++;
                    $raw = \rtrim($raw, "\n");
                    if ($raw === '') {
                        continue;
                    }
                    $parsed = $this->parseLine($raw, $file, $lineNumber);
                    if ($parsed !== null) {
                        $lines[] = $parsed;
                    }
                }
            } finally {
                \fclose($handle);
            }
        }
        \usort(
            $lines,
            static fn(TranscriptLine $a, TranscriptLine $b): int =>
                $a->timestamp <=> $b->timestamp
                ?: $a->processId <=> $b->processId
                ?: $a->sequence <=> $b->sequence,
        );
        return $lines;
    }

    /**
     * @return list<TranscriptLine>
     */
    public function findBySection(TranscriptSection $section): array
    {
        return \array_values(\array_filter(
            $this->getLines(),
            static fn(TranscriptLine $line): bool => $line->section === $section,
        ));
    }

    /**
     * @return list<TranscriptLine>
     */
    public function findByAttribute(string $key, string|int|float|bool|null $value): array
    {
        return \array_values(\array_filter(
            $this->getLines(),
            static fn(TranscriptLine $line): bool => ($line->attributes[$key] ?? null) === $value,
        ));
    }

    /**
     * @return list<TranscriptLine>
     */
    public function findBySectionAndAttribute(TranscriptSection $section, string $key, string|int|float|bool|null $value): array
    {
        return \array_values(\array_filter(
            $this->getLines(),
            static fn(TranscriptLine $line): bool =>
                $line->section === $section && ($line->attributes[$key] ?? null) === $value,
        ));
    }

    public function waitForQuiescence(int $timeoutMs = 5000, ?TranscriptSection $sentinel = TranscriptSection::TEST_END): bool
    {
        $deadlineUs = \microtime(true) * 1_000_000 + $timeoutMs * 1000;
        while (\microtime(true) * 1_000_000 < $deadlineUs) {
            if ($this->findBySection($sentinel) !== []) {
                return true;
            }
            \usleep(50_000);
        }
        return false;
    }

    /**
     * Assert that lines matching each step occur in order (by global sort).
     *
     * @param list<array{section: TranscriptSection, attributes?: array<string, scalar|null>}> $steps
     */
    public function hasSequence(array $steps): bool
    {
        $cursor = 0;
        $lines = $this->getLines();
        foreach ($steps as $step) {
            $matched = false;
            for (; $cursor < \count($lines); $cursor++) {
                $line = $lines[$cursor];
                if ($line->section !== $step['section']) {
                    continue;
                }
                $attributesMatch = true;
                foreach ($step['attributes'] ?? [] as $key => $value) {
                    if (($line->attributes[$key] ?? null) !== $value) {
                        $attributesMatch = false;
                        break;
                    }
                }
                if (!$attributesMatch) {
                    continue;
                }
                $matched = true;
                $cursor++;
                break;
            }
            if (!$matched) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return list<string>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Return lines that fall between the TEST_START and TEST_END boundaries for a specific test.
     * If multiple boundary pairs exist (re-runs), the latest pair wins.
     *
     * @return list<TranscriptLine>
     */
    public function linesForTest(string $class, string $method): array
    {
        $lines = $this->getLines();
        $startLine = null;
        $endLine = null;
        foreach ($lines as $line) {
            if ($line->section === TranscriptSection::TEST_START
                && ($line->attributes['class'] ?? null) === $class
                && ($line->attributes['method'] ?? null) === $method
            ) {
                $startLine = $line;
                $endLine = null;
                continue;
            }
            if ($line->section === TranscriptSection::TEST_END
                && ($line->attributes['class'] ?? null) === $class
                && ($line->attributes['method'] ?? null) === $method
            ) {
                $endLine = $line;
            }
        }
        if ($startLine === null) {
            return [];
        }
        $startTimestamp = $startLine->timestamp;
        $endTimestamp = $endLine?->timestamp;
        return \array_values(\array_filter(
            $lines,
            static function (TranscriptLine $candidate) use ($startTimestamp, $endTimestamp): bool {
                if ($candidate->timestamp < $startTimestamp) {
                    return false;
                }
                if ($endTimestamp !== null && $candidate->timestamp > $endTimestamp) {
                    return false;
                }
                return true;
            },
        ));
    }

    private function parseLine(string $raw, string $file, int $lineNumber): ?TranscriptLine
    {
        if (!\preg_match(
            '/^(?P<ts>\S+)\s+(?P<pid>\d+)\s+(?P<seq>\d+)\s+\[(?P<section>[A-Z_]+)\](?P<tail>.*)$/',
            $raw,
            $matches,
        )) {
            throw new MalformedTranscriptException(
                'Line does not match transcript schema',
                $raw,
                $lineNumber,
                $file,
            );
        }

        $sectionEnum = TranscriptSection::tryFrom($matches['section']);
        if ($sectionEnum === null) {
            throw new MalformedTranscriptException(
                'Unknown section: ' . $matches['section'],
                $raw,
                $lineNumber,
                $file,
            );
        }

        $tail = \ltrim($matches['tail']);
        $payload = null;
        $attributesPart = $tail;
        $payloadMarker = ' payload=';
        $payloadPosition = \strpos($tail, $payloadMarker);
        if ($payloadPosition !== false) {
            $attributesPart = \substr($tail, 0, $payloadPosition);
            $payloadJson = \substr($tail, $payloadPosition + \strlen($payloadMarker));
            $decoded = \json_decode($payloadJson, true);
            if ($decoded !== null || \json_last_error() === \JSON_ERROR_NONE) {
                $payload = \is_array($decoded) ? $decoded : ['value' => $decoded];
            } else {
                $payload = ['raw' => $payloadJson];
            }
        } elseif (\str_starts_with($tail, 'payload=')) {
            $payloadJson = \substr($tail, 8);
            $decoded = \json_decode($payloadJson, true);
            $payload = \is_array($decoded) ? $decoded : ['raw' => $payloadJson];
            $attributesPart = '';
        }

        $attributes = $this->parseAttributes($attributesPart);

        try {
            $timestamp = new \DateTimeImmutable($matches['ts']);
        } catch (\Throwable) {
            throw new MalformedTranscriptException(
                'Invalid timestamp',
                $raw,
                $lineNumber,
                $file,
            );
        }

        return new TranscriptLine(
            timestamp: $timestamp,
            processId: (int) $matches['pid'],
            sequence: (int) $matches['seq'],
            section: $sectionEnum,
            attributes: $attributes,
            payload: $payload,
            rawLine: $raw,
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private function parseAttributes(string $attributesPart): array
    {
        $attributes = [];
        $position = 0;
        $length = \strlen($attributesPart);
        while ($position < $length) {
            while ($position < $length && $attributesPart[$position] === ' ') {
                $position++;
            }
            if ($position >= $length) {
                break;
            }
            $equalsPosition = \strpos($attributesPart, '=', $position);
            if ($equalsPosition === false) {
                break;
            }
            $key = \substr($attributesPart, $position, $equalsPosition - $position);
            $valuePosition = $equalsPosition + 1;
            if ($valuePosition < $length && $attributesPart[$valuePosition] === '"') {
                $valuePosition++;
                $valueStart = $valuePosition;
                $value = '';
                while ($valuePosition < $length) {
                    $character = $attributesPart[$valuePosition];
                    if ($character === '\\' && $valuePosition + 1 < $length) {
                        $value .= $attributesPart[$valuePosition + 1];
                        $valuePosition += 2;
                        continue;
                    }
                    if ($character === '"') {
                        $valuePosition++;
                        break;
                    }
                    $value .= $character;
                    $valuePosition++;
                }
            } else {
                $spacePosition = \strpos($attributesPart, ' ', $valuePosition);
                if ($spacePosition === false) {
                    $value = \substr($attributesPart, $valuePosition);
                    $valuePosition = $length;
                } else {
                    $value = \substr($attributesPart, $valuePosition, $spacePosition - $valuePosition);
                    $valuePosition = $spacePosition;
                }
            }
            $attributes[$key] = $this->coerceAttributeValue($value);
            $position = $valuePosition;
        }
        return $attributes;
    }

    private function coerceAttributeValue(string $value): string|int|float|bool|null
    {
        if ($value === 'null') {
            return null;
        }
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if ($value !== '' && \preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        if ($value !== '' && \preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            return (float) $value;
        }
        return $value;
    }
}
