<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;

final class ContextSignatureConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    public const ENCODING = 'test/context-signature';
    public const NO_CONTEXT = 'none';

    private string $signature = self::NO_CONTEXT;

    public function getEncodingType(): string
    {
        return self::ENCODING;
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return null;
    }

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->signature = $context instanceof ActivitySerializationContext
            ? \sprintf('act|%s|%s|%s', $context->namespace, $context->activityType, $context->taskQueue)
            : self::NO_CONTEXT;

        return $clone;
    }

    public function toPayload($value): ?Payload
    {
        return (new Payload())->setData('')->setMetadata(['encoding' => self::ENCODING]);
    }

    public function fromPayload(Payload $payload, Type $type): mixed
    {
        return $this->signature;
    }
}
