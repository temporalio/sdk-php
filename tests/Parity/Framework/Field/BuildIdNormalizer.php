<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

/**
 * Replaces `workerVersion.buildId` hex blobs with the literal `<BUILD_ID>`.
 */
final class BuildIdNormalizer implements FieldNormalizerInterface
{
    public const PLACEHOLDER = '<BUILD_ID>';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (\is_string($value) && $value !== '') {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/buildId \"{$value}\" -> " . self::PLACEHOLDER);
            return self::PLACEHOLDER;
        }

        return $value;
    }
}
