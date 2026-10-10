<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(DataConverter::class)]
final class DataConverterSerializationContextTestCase extends AbstractUnit
{
    public function testWithNewContextReturnsDifferentInstance(): void
    {
        $converter = new DataConverter(new NullConverter());
        $context = new WorkflowSerializationContext('default', 'wf-1');

        self::assertNotSame($converter, $converter->withSerializationContext($context));
    }

    public function testWithIdenticalContextReturnsSameInstance(): void
    {
        $context = new WorkflowSerializationContext('default', 'wf-1');
        $converter = (new DataConverter(new NullConverter()))->withSerializationContext($context);

        self::assertSame($converter, $converter->withSerializationContext($context));
    }

    public function testAwarePayloadConverterIsRewrappedWithContext(): void
    {
        $aware = new RecordingAwarePayloadConverter();
        $converter = new DataConverter($aware);
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $bound = $converter->withSerializationContext($context);
        $bound->toPayload('value');

        self::assertNull($aware->lastUsedContext);
    }

    public function testPlainPayloadConverterIsLeftIntact(): void
    {
        $plain = new RecordingPlainPayloadConverter();
        $aware = new RecordingAwarePayloadConverter();
        $converter = new DataConverter($plain, $aware);
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $bound = $converter->withSerializationContext($context);
        $bound->toPayload('value');

        self::assertSame(1, $plain->callCount);
    }

    public function testReWrappedConverterReceivesContextAtConversionTime(): void
    {
        $aware = new RecordingAwarePayloadConverter();
        $converter = new DataConverter($aware);
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $bound = $converter->withSerializationContext($context);
        $bound->toPayload('value');

        $rewrapped = $aware->lastClone;
        self::assertNotNull($rewrapped);
        self::assertSame($context, $rewrapped->boundContext);
        self::assertSame(1, $rewrapped->callCount);
    }
}

final class RecordingAwarePayloadConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    public ?SerializationContext $boundContext = null;
    public ?SerializationContext $lastUsedContext = null;
    public int $callCount = 0;
    public ?self $lastClone = null;

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->boundContext = $context;
        $this->lastClone = $clone;
        return $clone;
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->boundContext;
    }

    public function getEncodingType(): string
    {
        return EncodingKeys::METADATA_ENCODING_RAW;
    }

    public function toPayload($value): ?Payload
    {
        ++$this->callCount;
        $this->lastUsedContext = $this->boundContext;

        return new Payload();
    }

    public function fromPayload(Payload $payload, Type $type)
    {
        return null;
    }
}

final class RecordingPlainPayloadConverter implements PayloadConverterInterface
{
    public int $callCount = 0;

    public function getEncodingType(): string
    {
        return EncodingKeys::METADATA_ENCODING_NULL;
    }

    public function toPayload($value): ?Payload
    {
        ++$this->callCount;

        return null;
    }

    public function fromPayload(Payload $payload, Type $type)
    {
        return null;
    }
}
