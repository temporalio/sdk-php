<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

final class ActivityTypeNormalizer implements FieldNormalizerInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (!\is_array($value) || !isset($value['name']) || !\is_string($value['name'])) {
            return $value;
        }

        $name = $value['name'];
        $dot = \strrpos($name, '.');
        $bare = $dot === false ? $name : \substr($name, $dot + 1);

        if ($bare !== $name) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/activityType \"{$name}\" -> \"{$bare}\"");
        }

        $value['name'] = $bare;
        return $value;
    }
}
