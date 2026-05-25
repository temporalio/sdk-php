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

    private static ?TranscriptWriter $writer = null;

    private static ?LoggerInterface $stderr = null;

    private static bool $inHandler = false;

    private static bool $registered = false;

    public static function register(TranscriptWriter $writer, ?LoggerInterface $stderr = null): void
    {
        self::$writer = $writer;
        self::$stderr = $stderr ?? new NullLogger();

        if (self::$registered) {
            return;
        }
        self::$registered = true;

        \set_error_handler(static function (int $type, string $message, string $file, int $line): bool {
            if (self::$inHandler) {
                return false;
            }
            self::$inHandler = true;
            try {
                self::$writer?->writeError($type, $message, $file, $line);
            } finally {
                self::$inHandler = false;
            }
            return false;
        });

        \set_exception_handler(static function (\Throwable $throwable): void {
            if (self::$inHandler) {
                return;
            }
            self::$inHandler = true;
            try {
                self::$writer?->writeFatal($throwable);
                self::$writer?->flush();
            } finally {
                self::$inHandler = false;
            }
            self::$stderr?->critical('fatal', [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
            exit(1);
        });

        \register_shutdown_function(static function (): void {
            $error = \error_get_last();
            if ($error === null) {
                self::$writer?->flush();
                return;
            }
            if (!\in_array((int) $error['type'], self::FATAL_ERROR_TYPES, true)) {
                self::$writer?->flush();
                return;
            }
            if (self::$inHandler) {
                return;
            }
            self::$inHandler = true;
            try {
                self::$writer?->writeFatalFromError($error);
                self::$writer?->flush();
            } finally {
                self::$inHandler = false;
            }
        });

        $writer->writeMeta('fatal_handler_registered', [
            'pid' => \getmypid() ?: 0,
        ]);
    }

    public static function rebindWriter(TranscriptWriter $writer): void
    {
        self::$writer = $writer;
        $writer->writeMeta('fatal_handler_rebound', [
            'pid' => \getmypid() ?: 0,
        ]);
    }
}
