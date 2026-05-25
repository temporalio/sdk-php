<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

final class TranscriptRun
{
    public const MERGED_DIRECTORY = '_merged';

    public function __construct(
        public readonly string $id,
        public readonly string $directory,
        public readonly ?int $mtime,
    ) {}

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $files = \glob($this->directory . '/*.log');
        return $files === false ? [] : \array_values($files);
    }

    public function totalBytes(): int
    {
        $bytes = 0;
        foreach ($this->files() as $file) {
            $bytes += (int) @\filesize($file);
        }
        return $bytes;
    }

    public function reader(): TranscriptReader
    {
        return new TranscriptReader($this->directory);
    }

    public function merge(): string
    {
        $mergedDirectory = $this->directory . '/' . self::MERGED_DIRECTORY;
        if (!\is_dir($mergedDirectory)) {
            @\mkdir($mergedDirectory, 0777, true);
        }
        $path = $mergedDirectory . '/transcript.log';
        $handle = \fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open merged file: {$path}");
        }
        if (!\flock($handle, \LOCK_EX)) {
            \fclose($handle);
            throw new \RuntimeException("Failed to acquire lock on merged file: {$path}");
        }
        try {
            foreach ($this->reader()->getLines() as $line) {
                $payload = $line->rawLine . "\n";
                $written = \fwrite($handle, $payload);
                if ($written === false || $written < \strlen($payload)) {
                    throw new \RuntimeException("Short write while merging transcript at {$path}");
                }
            }
        } finally {
            \flock($handle, \LOCK_UN);
            \fclose($handle);
        }
        return $path;
    }
}
