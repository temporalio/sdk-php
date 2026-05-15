<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

/**
 * Replaces protojson-encoded durations (e.g. `'15s'`, `'0s'`, `'10.500s'`,
 * `'1.500000001s'`) with the literal placeholder `<DURATION>`.
 *
 * Different SDKs serialise the same duration with different fractional widths,
 * so we collapse both the value and any precision differences in one step.
 */
final class DurationNormalizer implements FieldNormalizerInterface
{
    public const PLACEHOLDER = '<DURATION>';

    private const DURATION = '/^-?\d+(?:\.\d+)?s$/';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (\is_string($value) && \preg_match(self::DURATION, $value) === 1) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/duration \"{$value}\" -> " . self::PLACEHOLDER);
            return self::PLACEHOLDER;
        }

        return $value;
    }
}
