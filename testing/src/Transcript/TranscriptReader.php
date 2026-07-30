<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

final class TranscriptReader
{
    /** @var list<string> */
    private array $files;

    public function __construct(string $directory)
    {
        $live = \glob($directory . '/*.log');
        $rotated = \glob($directory . '/*.log.*');
        $this->files = \array_values(\array_merge(
            \is_array($live) ? $live : [],
            \is_array($rotated) ? $rotated : [],
        ));
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
                    $lines[] = $this->parseLine($raw, $file, $lineNumber);
                }
            } finally {
                \fclose($handle);
            }
        }
        \usort($lines, static function (TranscriptLine $a, TranscriptLine $b): int {
            $byTimestamp = $a->timestamp <=> $b->timestamp;
            if ($byTimestamp !== 0) {
                return $byTimestamp;
            }
            $byProcess = $a->processId <=> $b->processId;
            if ($byProcess !== 0) {
                return $byProcess;
            }
            return $a->sequence <=> $b->sequence;
        });
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

    private function parseLine(string $raw, string $file, int $lineNumber): TranscriptLine
    {
        $decoded = \json_decode($raw, true);
        if (!\is_array($decoded)) {
            throw new MalformedTranscriptException(
                'Line is not a valid JSON object: ' . \json_last_error_msg(),
                $raw,
                $lineNumber,
                $file,
            );
        }

        $sectionValue = $decoded['section'] ?? null;
        if (!\is_string($sectionValue)) {
            throw new MalformedTranscriptException('Missing section', $raw, $lineNumber, $file);
        }
        $sectionEnum = TranscriptSection::tryFrom($sectionValue);
        if ($sectionEnum === null) {
            throw new MalformedTranscriptException('Unknown section: ' . $sectionValue, $raw, $lineNumber, $file);
        }

        $timestampRaw = $decoded['ts'] ?? null;
        if (!\is_string($timestampRaw) || $timestampRaw === '') {
            throw new MalformedTranscriptException('Missing or empty timestamp', $raw, $lineNumber, $file);
        }
        try {
            $timestamp = new \DateTimeImmutable($timestampRaw);
        } catch (\Throwable) {
            throw new MalformedTranscriptException('Invalid timestamp: ' . $timestampRaw, $raw, $lineNumber, $file);
        }

        $attributes = $decoded['attributes'] ?? [];
        if (!\is_array($attributes)) {
            throw new MalformedTranscriptException('attributes must be an object', $raw, $lineNumber, $file);
        }
        foreach ($attributes as $key => $value) {
            if ($value !== null && !\is_scalar($value)) {
                throw new MalformedTranscriptException(
                    \sprintf('attribute "%s" must be scalar or null, %s given', (string) $key, \get_debug_type($value)),
                    $raw,
                    $lineNumber,
                    $file,
                );
            }
        }

        $payload = $decoded['payload'] ?? null;
        if ($payload !== null && !\is_array($payload)) {
            throw new MalformedTranscriptException(
                'payload must be an object or null, ' . \get_debug_type($payload) . ' given',
                $raw,
                $lineNumber,
                $file,
            );
        }

        return new TranscriptLine(
            timestamp: $timestamp,
            processId: (int) ($decoded['pid'] ?? 0),
            sequence: (int) ($decoded['seq'] ?? 0),
            section: $sectionEnum,
            attributes: $attributes,
            payload: $payload,
            rawLine: $raw,
        );
    }
}
