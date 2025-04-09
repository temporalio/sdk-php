<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

/**
 * Factory for creating logger instances used in acceptance tests.
 *
 * Provides methods to create both server-side and client-side loggers with appropriate
 * configuration for test environments.
 */
final class LoggerFactory
{
    /** @var non-empty-string Default relative path for test logs */
    private const DEFAULT_LOG_DIR = 'runtime/tests/logs';

    /**
     * Create a server-side file logger.
     *
     * @param non-empty-string $taskQueue Task queue name used to identify the log file
     * @param non-empty-string|null $baseDir Optional base directory, defaults to project root
     * @return FileLogger Configured file logger instance
     */
    public static function createServerLogger(
        string $taskQueue,
        ?string $baseDir = null,
    ): FileLogger {
        $logDir = self::getLogDirectory($baseDir);
        return new FileLogger($logDir, $taskQueue);
    }

    /**
     * Create a client-side logger for assertions.
     *
     * The client logger provides methods for reading and analyzing log entries
     * created by the server-side logger.
     *
     * @param non-empty-string $taskQueue Task queue name used to identify the log file
     * @param non-empty-string|null $baseDir Optional base directory, defaults to project root
     * @return ClientLogger Configured client logger instance
     */
    public static function createClientLogger(
        string $taskQueue,
        ?string $baseDir = null,
    ): ClientLogger {
        $logDir = self::getLogDirectory($baseDir);
        return new ClientLogger($logDir, $taskQueue);
    }

    /**
     * Generate the log filename for a specific task queue.
     *
     * Uses sha1 hash of task queue name to handle special characters.
     *
     * @param non-empty-string $dir Directory path
     * @param non-empty-string $taskQueue Task queue name
     * @return non-empty-string Full path to the log file
     */
    public static function getLogFilename(
        string $dir,
        string $taskQueue,
    ): string {
        $filename = \sha1($taskQueue) . '.log';

        return \preg_replace(
            '#/{2,}#',
            '/',
            \str_replace('\\', '/', "{$dir}/{$filename}"),
        );
    }

    /**
     * Get the absolute path to the log directory.
     *
     * Creates the directory if it doesn't exist.
     *
     * @param non-empty-string|null $baseDir Optional base directory, defaults to project root
     * @return non-empty-string Absolute path to the log directory
     */
    private static function getLogDirectory(?string $baseDir = null): string
    {
        $baseDir ??= \dirname(__DIR__, 4); // Go up to project root
        $logDir = $baseDir . '/' . self::DEFAULT_LOG_DIR;

        // Ensure directory exists
        if (!\is_dir($logDir)) {
            \mkdir($logDir, 0777, true);
        }

        return $logDir;
    }
}
