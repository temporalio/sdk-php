<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    private const DEFAULT_LOG_DIR = 'runtime/tests/logs';

    public static function createServerLogger(
        string $taskQueue,
        ?string $baseDir = null,
    ): FileLogger {
        return new FileLogger(self::getLogDirectory($baseDir), $taskQueue);
    }

    public static function createClientLogger(
        string $taskQueue,
        ?string $baseDir = null,
    ): ClientLogger {
        return new ClientLogger(self::getLogDirectory($baseDir), $taskQueue);
    }

    public static function getLogFilename(string $dir, string $taskQueue): string
    {
        $filename = \sha1($taskQueue) . '.log';
        return \preg_replace(
            '#/{2,}#',
            '/',
            \str_replace('\\', '/', "{$dir}/{$filename}"),
        );
    }

    public static function createServerLoggerWithTranscript(
        string $taskQueue,
        TranscriptWriter $transcript,
        LoggerInterface $stderr,
        ?string $baseDir = null,
    ): FanoutLogger {
        return new FanoutLogger(
            $stderr,
            self::createServerLogger($taskQueue, $baseDir),
            new TranscriptAdapter($transcript, $stderr),
        );
    }

    private static function getLogDirectory(?string $baseDir = null): string
    {
        $baseDir ??= \dirname(__DIR__, 4);
        $logDir = $baseDir . '/' . self::DEFAULT_LOG_DIR;
        if (!\is_dir($logDir)) {
            \mkdir($logDir, 0777, true);
        }
        return $logDir;
    }
}
