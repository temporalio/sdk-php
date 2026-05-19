<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Field;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Tests\Parity\Framework\FieldNormalizerInterface;
use Temporal\Tests\Parity\Framework\Source;

/**
 * Rewrites `taskQueue.name` (not the bare `name` key — `workflowType.name`
 * must survive). PHP derives the task-queue from the test class FQN; Java
 * passes a raw string.
 */
final class TaskQueueNormalizer implements FieldNormalizerInterface
{
    public const PLACEHOLDER = '<TASK_QUEUE>';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function normalize(mixed $value, Source $source): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (\array_key_exists('name', $value) && \is_string($value['name'])) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/taskQueue.name \"{$value['name']}\" -> " . self::PLACEHOLDER);
            $value['name'] = self::PLACEHOLDER;
        }

        if (\array_key_exists('normalName', $value)) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/taskQueue.normalName dropped (java/go emit, php omits)");
            unset($value['normalName']);
        }

        if (\array_key_exists('kind', $value)) {
            $this->logger?->log(LogLevel::DEBUG, "parity: {$source->value}/taskQueue.kind \"{$value['kind']}\" dropped (sticky-vs-normal varies per SDK)");
            unset($value['kind']);
        }

        return $value;
    }
}
