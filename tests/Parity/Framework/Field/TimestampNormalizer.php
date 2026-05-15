<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

final class TimestampNormalizer implements FieldNormalizerInterface
{
    public const PLACEHOLDER = '<TIMESTAMP>';

    private const RFC3339 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (\is_string($value) && \preg_match(self::RFC3339, $value) === 1) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/timestamp \"{$value}\" -> " . self::PLACEHOLDER);
            return self::PLACEHOLDER;
        }

        if (\is_array($value) && (isset($value['seconds']) || isset($value['nanos'])) && \count($value) <= 2) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/timestamp proto-map -> " . self::PLACEHOLDER);
            return self::PLACEHOLDER;
        }

        return $value;
    }
}
