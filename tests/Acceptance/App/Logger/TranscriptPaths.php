<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

/**
 * Single owner of transcript path/id grammar:
 *  - run id format and sanitization
 *  - per-process writer filename layout
 *  - rotation suffix
 *  - merged-directory + merged-file conventions
 *  - the reserved underscore-prefix used to mark control directories
 */
final class TranscriptPaths
{
    private const SLUG_PATTERN = '~[^A-Za-z0-9_-]~';
    private const SLUG_REPLACEMENT = '_';
    private const RUN_ID_MAX_LENGTH = 64;
    private const PROCESS_LABEL_MAX_LENGTH = 40;
    private const LOG_EXTENSION = '.log';
    private const MERGED_DIRECTORY = '_merged';
    private const MERGED_FILENAME = 'transcript.log';
    private const RESERVED_PREFIX = '_';

    public static function generateRunId(): string
    {
        return \date('Ymd-His') . '-' . \bin2hex(\random_bytes(2));
    }

    public static function sanitizeRunId(string $runId): string
    {
        $slug = self::sanitizeSegment($runId);
        if ($slug === '') {
            throw new \InvalidArgumentException(
                'Run id sanitizes to an empty string: ' . \var_export($runId, true),
            );
        }
        if (\str_starts_with($slug, self::RESERVED_PREFIX)) {
            $slug = 'r' . $slug;
        }
        return self::truncate($slug, self::RUN_ID_MAX_LENGTH);
    }

    public static function sanitizeProcessLabel(string $label): string
    {
        $slug = self::sanitizeSegment($label);
        if ($slug === '') {
            $slug = 'process';
        }
        return self::truncate($slug, self::PROCESS_LABEL_MAX_LENGTH);
    }

    public static function currentEpochMs(): int
    {
        return (int) \floor(\microtime(true) * 1000);
    }

    public static function runDirectory(string $baseDirectory, string $runId): string
    {
        return $baseDirectory . '/' . self::sanitizeRunId($runId);
    }

    public static function writerFile(string $runDirectory, string $processLabel): string
    {
        return $runDirectory
            . '/' . self::sanitizeProcessLabel($processLabel)
            . '__pid' . (\getmypid() ?: 0)
            . '__' . self::currentEpochMs()
            . self::LOG_EXTENSION;
    }

    public static function rotatedFile(string $currentPath, int $rotationCounter): string
    {
        return $currentPath . '.' . $rotationCounter;
    }

    public static function mergedDirectory(string $runDirectory): string
    {
        return $runDirectory . '/' . self::MERGED_DIRECTORY;
    }

    public static function mergedFile(string $runDirectory): string
    {
        return self::mergedDirectory($runDirectory) . '/' . self::MERGED_FILENAME;
    }

    public static function isReservedEntry(string $entry): bool
    {
        return \str_starts_with($entry, self::RESERVED_PREFIX);
    }

    private static function sanitizeSegment(string $value): string
    {
        return \preg_replace(self::SLUG_PATTERN, self::SLUG_REPLACEMENT, $value) ?? '';
    }

    private static function truncate(string $value, int $max): string
    {
        return \strlen($value) > $max ? \substr($value, 0, $max) : $value;
    }
}
