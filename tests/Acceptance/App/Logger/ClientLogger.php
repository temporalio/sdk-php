<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

/**
 * Logger client for reading and analyzing log entries in acceptance tests.
 *
 * Provides methods to read, filter, and analyze log records produced by the {@see FileLogger}
 * during test execution.
 */
final class ClientLogger
{
    /** @var non-empty-string Path to the log file */
    private readonly string $logFile;

    /**
     * @param non-empty-string $dir Directory where logs are stored
     * @param non-empty-string $taskQueue TaskQueue name used as log file identifier
     */
    public function __construct(
        private readonly string $dir,
        private readonly string $taskQueue,
    ) {
        $this->logFile = LoggerFactory::getLogFilename($this->dir, $this->taskQueue);
    }

    /**
     * Get the log file path.
     *
     * @return non-empty-string Absolute path to the log file
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Retrieve all log records from the log file.
     *
     * @return list<LogRecord> All log records in chronological order
     */
    public function getRecords(): array
    {
        return \iterator_to_array($this->readAll(), false);
    }

    /**
     * Find log records by matching message content.
     *
     * @param non-empty-string $messagePattern Regular expression to match against log messages
     * @return list<LogRecord> Matching log records
     */
    public function findByMessage(string $messagePattern): array
    {
        $result = [];
        foreach ($this->readAll() as $record) {
            if (\preg_match($messagePattern, $record->message) === 1) {
                $result[] = $record;
            }
        }

        return $result;
    }

    /**
     * Find log records by specific log level.
     *
     * @param string $level Log level to filter by (debug, info, warning, error, etc.)
     * @return list<LogRecord> Matching log records
     */
    public function findByLevel(string $level): array
    {
        $result = [];
        foreach ($this->readAll() as $record) {
            if ($record->level === $level) {
                $result[] = $record;
            }
        }

        return $result;
    }

    /**
     * Find log records by message content and context data.
     *
     * @param string $messagePattern Regular expression to match against log messages
     * @param array<string, mixed> $contextKeys Keys that must exist in the context
     * @return list<LogRecord> Matching log records
     */
    public function findByMessageAndContext(string $messagePattern, array $contextKeys = []): array
    {
        $result = [];
        foreach ($this->readAll() as $record) {
            if (!\preg_match($messagePattern, $record->message)) {
                continue;
            }

            $hasAllKeys = true;
            foreach ($contextKeys as $key) {
                if (!isset($record->context[$key])) {
                    $hasAllKeys = false;
                    break;
                }
            }

            if ($hasAllKeys) {
                $result[] = $record;
            }
        }

        return $result;
    }

    /**
     * Check if a specific log message exists.
     *
     * @param string $messagePattern Regular expression to match against log messages
     * @return bool True if at least one matching message is found
     */
    public function hasMessage(string $messagePattern): bool
    {
        return \count($this->findByMessage($messagePattern)) > 0;
    }

    /**
     * Clear all logs for current task queue.
     *
     * Removes the log file if it exists.
     */
    public function clear(): void
    {
        if (\is_file($this->logFile)) {
            \unlink($this->logFile);
        }
    }

    /**
     * Read all log records from the log file.
     *
     * @return \Traversable<LogRecord> Generator yielding LogRecord objects
     */
    private function readAll(): \Traversable
    {
        if (!\is_file($this->logFile)) {
            return [];
        }

        $lines = \file($this->logFile);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = \trim($line);
            if ($line === '') {
                continue;
            }

            yield \unserialize($line, ['allowed_classes' => [LogRecord::class]]);
        }
    }
}
