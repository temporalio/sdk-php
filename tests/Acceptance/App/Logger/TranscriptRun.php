<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

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
        $mergedDirectory = $this->directory . '/_merged';
        if (!\is_dir($mergedDirectory)) {
            @\mkdir($mergedDirectory, 0777, true);
        }
        $path = $mergedDirectory . '/transcript.log';
        $handle = \fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open merged file: {$path}");
        }
        try {
            foreach ($this->reader()->getLines() as $line) {
                \fwrite($handle, $line->rawLine . "\n");
            }
        } finally {
            \fclose($handle);
        }
        return $path;
    }
}
