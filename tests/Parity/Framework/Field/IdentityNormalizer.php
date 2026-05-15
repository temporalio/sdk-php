<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

/**
 * Replaces worker identity strings with the literal `<IDENTITY>`.
 *
 * PHP RoadRunner workers report `roadrunner:<task-queue>:<uuid>`; Java/Go
 * workers report `<pid>@<host>`. Both forms are noise for parity assertions.
 */
final class IdentityNormalizer implements FieldNormalizerInterface
{
    public const PLACEHOLDER = '<IDENTITY>';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (\is_string($value) && $value !== '') {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/identity \"{$value}\" -> " . self::PLACEHOLDER);
            return self::PLACEHOLDER;
        }

        return $value;
    }
}
