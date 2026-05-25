<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final class TranscriptStore
{
    private const DEFAULT_BASE_RELATIVE = 'runtime/tests/transcripts';
    private const RUN_ID_ENV = 'TEMPORAL_TRANSCRIPT_RUN_ID';
    private const BASE_DIR_ENV = 'TEMPORAL_TRANSCRIPT_DIR';

    private readonly LoggerInterface $stderr;

    private readonly Filesystem $filesystem;

    public function __construct(
        public readonly string $baseDirectory,
        ?LoggerInterface $stderr = null,
    ) {
        $this->stderr = $stderr ?? new NullLogger();
        $this->filesystem = new Filesystem();
        try {
            $this->filesystem->mkdir($this->baseDirectory);
        } catch (IOException $ioError) {
            $this->stderr->warning('transcript-store: base directory create failed', [
                'path' => $this->baseDirectory,
                'message' => $ioError->getMessage(),
            ]);
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

    public static function currentRunIdFromEnvironment(): ?string
    {
        $runId = \getenv(self::RUN_ID_ENV);
        return \is_string($runId) && $runId !== '' ? $runId : null;
    }

    public function runDirectory(string $runId): string
    {
        return TranscriptPaths::runDirectory($this->baseDirectory, $runId);
    }

    public function ensureRunDirectory(string $runId): string
    {
        $directory = $this->runDirectory($runId);
        $this->filesystem->mkdir($directory);
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
            if ($entry === '.' || $entry === '..' || TranscriptPaths::isReservedEntry($entry)) {
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
            static function (TranscriptRun $a, TranscriptRun $b): int {
                $byMtime = ($b->mtime ?? 0) <=> ($a->mtime ?? 0);
                if ($byMtime !== 0) {
                    return $byMtime;
                }
                return $b->id <=> $a->id;
            },
        );
        return $runs;
    }

    public function latestRun(): ?TranscriptRun
    {
        return $this->listRuns()[0] ?? null;
    }

    public function findRun(string $runId): ?TranscriptRun
    {
        $directory = TranscriptPaths::runDirectory($this->baseDirectory, $runId);
        if (!\is_dir($directory)) {
            return null;
        }
        $mtime = @\filemtime($directory);
        return new TranscriptRun(
            id: \basename($directory),
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
            try {
                $this->filesystem->remove($run->directory);
                $deleted++;
            } catch (IOException $ioError) {
                $this->stderr->warning('transcript-store: prune failed', [
                    'path' => $run->directory,
                    'message' => $ioError->getMessage(),
                ]);
            }
        }
        return $deleted;
    }

    public function createWriter(string $runId, string $processLabel): TranscriptWriter
    {
        $directory = $this->ensureRunDirectory($runId);
        return new TranscriptWriter(TranscriptPaths::writerFile($directory, $processLabel), $this->stderr);
    }
}
