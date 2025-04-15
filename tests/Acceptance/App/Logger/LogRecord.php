<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

/**
 * Represents a log entry with timestamp, level, message, and context.
 *
 * This immutable class serves as a data container for log entries stored in the test logs.
 */
final class LogRecord
{
    /**
     * @param int<0, max> $timestamp Unix timestamp when the log was created
     * @param non-empty-string $level Log level (debug, info, warning, error, etc.)
     * @param string $message Log message content
     * @param array<non-empty-string, mixed> $context Additional contextual data for the log entry
     */
    public function __construct(
        public readonly int $timestamp,
        public readonly string $level,
        public readonly string $message,
        public readonly array $context = [],
    ) {}
}
