<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;

final class FatalHandler
{
    private const FATAL_ERROR_TYPES = [
        \E_ERROR,
        \E_PARSE,
        \E_CORE_ERROR,
        \E_COMPILE_ERROR,
        \E_USER_ERROR,
    ];

    private static bool $inHandler = false;

    private static bool $registered = false;

    public static function register(TranscriptWriter $writer, LoggerInterface $stderr): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        \set_error_handler(static function (int $type, string $message, string $file, int $line) use ($writer): bool {
            if (self::$inHandler) {
                return false;
            }
            self::$inHandler = true;
            try {
                $writer->writeError($type, $message, $file, $line);
            } finally {
                self::$inHandler = false;
            }
            return false;
        });

        \set_exception_handler(static function (\Throwable $throwable) use ($stderr, $writer): void {
            if (self::$inHandler) {
                return;
            }
            self::$inHandler = true;
            try {
                $writer->writeFatal($throwable);
                $writer->flush();
            } finally {
                self::$inHandler = false;
            }
            $stderr->critical('fatal', [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
            exit(1);
        });

        \register_shutdown_function(static function () use ($writer): void {
            $error = \error_get_last();
            if ($error === null) {
                $writer->flush();
                return;
            }
            if (!\in_array((int) $error['type'], self::FATAL_ERROR_TYPES, true)) {
                $writer->flush();
                return;
            }
            if (self::$inHandler) {
                return;
            }
            self::$inHandler = true;
            try {
                $writer->writeFatalFromError($error);
                $writer->flush();
            } finally {
                self::$inHandler = false;
            }
        });

        $writer->writeMeta('fatal_handler_registered', [
            'pid' => \getmypid() ?: 0,
        ]);
    }
}
