<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

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

        try {
            $timestamp = new \DateTimeImmutable((string) ($decoded['ts'] ?? ''));
        } catch (\Throwable) {
            throw new MalformedTranscriptException('Invalid timestamp', $raw, $lineNumber, $file);
        }

        $attrs = $decoded['attrs'] ?? [];
        if (!\is_array($attrs)) {
            $attrs = [];
        }

        $payload = $decoded['payload'] ?? null;
        if ($payload !== null && !\is_array($payload)) {
            $payload = ['value' => $payload];
        }

        return new TranscriptLine(
            timestamp: $timestamp,
            processId: (int) ($decoded['pid'] ?? 0),
            sequence: (int) ($decoded['seq'] ?? 0),
            section: $sectionEnum,
            attributes: $attrs,
            payload: $payload,
            rawLine: $raw,
        );
    }
}
