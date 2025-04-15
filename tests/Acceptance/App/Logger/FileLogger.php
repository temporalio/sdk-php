<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * File-based logger implementation for testing purposes.
 *
 * Stores serialized log records in a file for later analysis. Each log entry is stored as a serialized LogRecord
 * on a separate line in the log file.
 */
final class FileLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var non-empty-string Path to the log file */
    private readonly string $logFile;

    /**
     * @param non-empty-string $dir Directory where logs will be stored
     * @param non-empty-string $taskQueue Task queue name used to identify the log file
     */
    public function __construct(
        private readonly string $dir,
        private readonly string $taskQueue = 'default',
    ) {
        // Create directory if it doesn't exist
        if (!\is_dir($dir)) {
            \mkdir($dir, 0777, true);
        }

        $this->logFile = LoggerFactory::getLogFilename($this->dir, $this->taskQueue);
    }

    /**
     * Get the absolute path to the log file.
     *
     * @return non-empty-string
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Records a log entry to the log file.
     *
     * Each log entry is serialized as a LogRecord object and appended to the log file with a newline separator.
     *
     * @param non-empty-string $level Log level
     * @param \Stringable|string $message Log message
     * @param array<string, mixed> $context Additional contextual data
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $record = new LogRecord(
            timestamp: \time(),
            level: (string) $level,
            message: (string) $message,
            context: $context,
        );

        \file_put_contents(
            $this->logFile,
            \serialize($record) . \PHP_EOL,
            \FILE_APPEND | \LOCK_EX,
        );
    }
}
