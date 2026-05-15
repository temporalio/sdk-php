<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

/**
 * Replaces the entire `sdkMetadata` map (which carries `sdkName`, `sdkVersion`
 * and, on some SDKs, a `langUsedFlags` array) with a fixed two-key map.
 *
 * `langUsedFlags` is dropped entirely — the PHP-via-RoadRunner worker reports
 * Go-side flags that have no counterpart in Java's metadata.
 */
final class SdkMetadataNormalizer implements FieldNormalizerInterface
{
    public const SDK_NAME = '<SDK_NAME>';
    public const SDK_VERSION = '<SDK_VERSION>';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/sdkMetadata collapsed");

        return [
            'sdkName' => self::SDK_NAME,
            'sdkVersion' => self::SDK_VERSION,
        ];
    }
}
