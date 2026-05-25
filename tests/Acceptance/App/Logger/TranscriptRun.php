<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final class TranscriptRun
{
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
        $live = \glob($this->directory . '/*.log');
        $rotated = \glob($this->directory . '/*.log.*');
        return \array_values(\array_merge(
            \is_array($live) ? $live : [],
            \is_array($rotated) ? $rotated : [],
        ));
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
        $mergedDirectory = TranscriptPaths::mergedDirectory($this->directory);
        try {
            (new Filesystem())->mkdir($mergedDirectory);
        } catch (IOException $ioError) {
            throw new \RuntimeException(
                "Failed to create merged directory: {$mergedDirectory} ({$ioError->getMessage()})",
                previous: $ioError,
            );
        }
        $path = TranscriptPaths::mergedFile($this->directory);
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
