<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class TranscriptStore
{
    private const DEFAULT_BASE_RELATIVE = 'runtime/tests/transcripts';
    private const RUN_ID_ENV = 'TEMPORAL_TRANSCRIPT_RUN_ID';
    private const BASE_DIR_ENV = 'TEMPORAL_TRANSCRIPT_DIR';

    private readonly LoggerInterface $stderr;

    public function __construct(
        public readonly string $baseDirectory,
        ?LoggerInterface $stderr = null,
    ) {
        $this->stderr = $stderr ?? new NullLogger();
        if (!\is_dir($this->baseDirectory)) {
            @\mkdir($this->baseDirectory, 0777, true);
        }
    }

    public static function create(?string $projectRoot = null, ?LoggerInterface $stderr = null): self
    {
        $projectRoot ??= \dirname(__DIR__, 4);
        $configured = \getenv(self::BASE_DIR_ENV);
        if (\is_string($configured) && $configured !== '') {
            $base = \str_starts_with($configured, '/')
                ? $configured
                : $projectRoot . '/' . $configured;
            return new self($base, $stderr);
        }
        return new self($projectRoot . '/' . self::DEFAULT_BASE_RELATIVE, $stderr);
    }

    public static function generateRunId(): string
    {
        return \date('Ymd-His') . '-' . \bin2hex(\random_bytes(2));
    }

    public static function currentRunIdFromEnvironment(): ?string
    {
        $runId = \getenv(self::RUN_ID_ENV);
        return \is_string($runId) && $runId !== '' ? $runId : null;
    }

    public function runDirectory(string $runId): string
    {
        return $this->baseDirectory . '/' . self::sanitizeRunId($runId);
    }

    public function ensureRunDirectory(string $runId): string
    {
        $directory = $this->runDirectory($runId);
        if (!\is_dir($directory)) {
            @\mkdir($directory, 0777, true);
        }
        return $directory;
    }

    /**
     * @return list<TranscriptRun>
     */
    public function listRuns(): array
    {
        $entries = @\scandir($this->baseDirectory);
        if ($entries === false) {
            return [];
        }
        $runs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || \str_starts_with($entry, '_')) {
                continue;
            }
            $path = $this->baseDirectory . '/' . $entry;
            if (!\is_dir($path)) {
                continue;
            }
            $mtime = @\filemtime($path);
            $runs[] = new TranscriptRun(
                id: $entry,
                directory: $path,
                mtime: $mtime === false ? null : $mtime,
            );
        }
        \usort(
            $runs,
            static fn(TranscriptRun $a, TranscriptRun $b): int => ($b->mtime ?? 0) <=> ($a->mtime ?? 0),
        );
        return $runs;
    }

    public function latestRun(): ?TranscriptRun
    {
        return $this->listRuns()[0] ?? null;
    }

    public function findRun(string $runId): ?TranscriptRun
    {
        $sanitized = self::sanitizeRunId($runId);
        $directory = $this->baseDirectory . '/' . $sanitized;
        if (!\is_dir($directory)) {
            return null;
        }
        $mtime = @\filemtime($directory);
        return new TranscriptRun(
            id: $sanitized,
            directory: $directory,
            mtime: $mtime === false ? null : $mtime,
        );
    }

    public function currentRun(): ?TranscriptRun
    {
        $runId = self::currentRunIdFromEnvironment();
        return $runId === null ? $this->latestRun() : $this->findRun($runId);
    }

    public function pruneOldRuns(int $keep): int
    {
        $keep = \max(0, $keep);
        $stale = \array_slice($this->listRuns(), $keep);
        $deleted = 0;
        foreach ($stale as $run) {
            if ($this->removeDirectoryRecursive($run->directory)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    public function createWriter(string $runId, string $processLabel): TranscriptWriter
    {
        $directory = $this->ensureRunDirectory($runId);
        return new TranscriptWriter(self::buildFilename($directory, $processLabel), $this->stderr);
    }

    private static function sanitizeRunId(string $runId): string
    {
        $slug = \preg_replace('~[^A-Za-z0-9_-]~', '_', $runId) ?? '';
        if ($slug === '') {
            return 'run';
        }
        if ($slug[0] === '_') {
            $slug = 'r' . $slug;
        }
        return \strlen($slug) > 64 ? \substr($slug, 0, 64) : $slug;
    }

    private static function buildFilename(string $directory, string $processLabel): string
    {
        $slug = \preg_replace('~[^A-Za-z0-9_-]~', '_', $processLabel) ?? 'process';
        if (\strlen($slug) > 40) {
            $slug = \substr($slug, 0, 40);
        }
        $processId = \getmypid() ?: 0;
        $startMs = (int) (\microtime(true) * 1000);
        return $directory . '/' . $slug . '__pid' . $processId . '__' . $startMs . '.log';
    }

    private function removeDirectoryRecursive(string $path): bool
    {
        if (!\is_dir($path)) {
            return false;
        }
        $entries = @\scandir($path);
        if ($entries === false) {
            return false;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            if (\is_dir($child)) {
                $this->removeDirectoryRecursive($child);
                continue;
            }
            @\unlink($child);
        }
        return @\rmdir($path);
    }
}
