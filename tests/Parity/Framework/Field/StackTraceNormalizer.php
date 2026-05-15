<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

/**
 * Folds PHP `#0 …` and Java `at <pkg>.…` stack frames (or any non-empty
 * `stackTrace`) to `<STACKTRACE_PRESENT>` / `<STACKTRACE_ABSENT>` so
 * traceback-bearing PHP messages compare equal to Java's plain messages.
 */
final class StackTraceNormalizer implements FieldNormalizerInterface
{
    public const PRESENT = '<STACKTRACE_PRESENT>';
    public const ABSENT = '<STACKTRACE_ABSENT>';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (!\is_string($value)) {
            return $value;
        }

        $hasPhpFrames = \preg_match('/^#\d+\s/m', $value) === 1;
        $hasJavaFrames = \preg_match('/\n\s*at [\w\.\$]+\(/m', $value) === 1;

        $marker = ($hasPhpFrames || $hasJavaFrames || $value !== '')
            ? self::PRESENT
            : self::ABSENT;

        $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/stackTrace -> {$marker}");

        return $marker;
    }
}
