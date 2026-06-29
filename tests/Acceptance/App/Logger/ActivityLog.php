<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Psr\Log\LoggerInterface;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;

final class ActivityLog
{
    private static ?LoggerInterface $logger = null;

    public static function bind(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function reset(): void
    {
        self::$logger = null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::resolve()?->debug($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::resolve()?->info($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::resolve()?->warning($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::resolve()?->error($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function critical(string $message, array $context = []): void
    {
        self::resolve()?->critical($message, $context);
    }

    private static function resolve(): ?LoggerInterface
    {
        if (self::$logger !== null) {
            return self::$logger;
        }
        try {
            $container = ContainerFacade::$container ?? null;
            if ($container !== null && $container->has(TranscriptWriter::class)) {
                $writer = $container->get(TranscriptWriter::class);
                self::$logger = new TranscriptAdapter($writer);
                return self::$logger;
            }
        } catch (\Throwable) {
            // intentionally swallow — caller should not fail when transcript binding is absent
        }
        \fwrite(\STDERR, '[meta] activity_log_unbound\n');
        return null;
    }
}
