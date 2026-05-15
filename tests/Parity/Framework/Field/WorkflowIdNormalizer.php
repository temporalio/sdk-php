<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

/**
 * Replaces every kind of Temporal-issued opaque identifier with the literal
 * `<ID>`. Applies indiscriminately to UUIDs, monotonic `taskId` integers,
 * `eventId` numerics and the like — the dispatcher decides which keys to
 * route here.
 */
final class WorkflowIdNormalizer implements FieldNormalizerInterface
{
    public const PLACEHOLDER = '<ID>';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (\is_string($value) || \is_int($value)) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/id \"{$value}\" -> " . self::PLACEHOLDER);
            return self::PLACEHOLDER;
        }

        return $value;
    }
}
