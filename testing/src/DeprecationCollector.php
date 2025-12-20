<?php

declare(strict_types=1);

namespace Temporal\Testing;

class DeprecationCollector
{
    /** @var DeprecationMessage[] */
    private static array $deprecations = [];

    public static function register(): void
    {
        \set_error_handler([self::class, 'handle'], E_USER_DEPRECATED);
    }

    public static function handle(int $errno, string $message, string $file, int $line): bool
    {
        if ($errno !== E_USER_DEPRECATED) {
            return false;
        }

        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        self::$deprecations[] = new DeprecationMessage($message, $file, $line, $trace);
        return true;
    }

    public static function getAll(): array
    {
        return self::$deprecations;
    }
}
