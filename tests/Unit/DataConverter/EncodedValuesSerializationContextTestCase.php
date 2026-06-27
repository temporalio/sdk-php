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
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(EncodedValues::class)]
final class EncodedValuesSerializationContextTestCase extends AbstractUnit
{
    public function testToPayloadsAppliesBoundContextAtConversionTime(): void
    {
        $converter = new ContextRecordingDataConverter();
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $values = EncodedValues::fromValues(['hello'], $converter);
        $values = $values->withSerializationContext($context);
        $values->toPayloads();

        self::assertSame($context, $converter->lastUsedContext);
    }

    public function testGetValueAppliesBoundContextAtConversionTime(): void
    {
        $converter = new ContextRecordingDataConverter();
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $payloads = EncodedValues::fromValues(['hello'], $converter)->toPayloads();
        $values = EncodedValues::fromPayloads($payloads, $converter);
        $values = $values->withSerializationContext($context);
        $values->getValue(0);

        self::assertSame($context, $converter->lastUsedContext);
    }

    public function testWithoutContextConverterSeesNull(): void
    {
        $converter = new ContextRecordingDataConverter();

        $values = EncodedValues::fromValues(['hello'], $converter);
        $values->toPayloads();

        self::assertNull($converter->lastUsedContext);
    }

    public function testChangingContextAfterUseRebindsConverter(): void
    {
        $recorder = new ContextUsageRecorder();
        $converter = new CloningContextRecordingDataConverter($recorder);
        $contextA = new WorkflowSerializationContext('default', 'wf-A');
        $contextB = new WorkflowSerializationContext('default', 'wf-B');

        $values = EncodedValues::fromValues(['hello'], $converter);

        $values = $values->withSerializationContext($contextA);
        $values->toPayloads();
        self::assertSame($contextA, $recorder->lastUsedContext);

        $values = $values->withSerializationContext($contextB);
        $values->toPayloads();
        self::assertSame($contextB, $recorder->lastUsedContext);
    }

    public function testChangingConverterAfterUseRebinds(): void
    {
        $recorderA = new ContextUsageRecorder();
        $recorderB = new ContextUsageRecorder();
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $values = EncodedValues::fromValues(['hello'], new CloningContextRecordingDataConverter($recorderA));
        $values = $values->withSerializationContext($context);
        $values->toPayloads();
        self::assertSame($context, $recorderA->lastUsedContext);

        $values->setDataConverter(new CloningContextRecordingDataConverter($recorderB));
        $values->toPayloads();
        self::assertSame($context, $recorderB->lastUsedContext);
    }

    public function testWithSerializationContextReturnsCloneAndLeavesOriginalUnchanged(): void
    {
        $context = new WorkflowSerializationContext('default', 'wf-1');
        $original = EncodedValues::fromValues(['x']);
        $clone = $original->withSerializationContext($context);

        self::assertNotSame($original, $clone);
        self::assertNull($original->getSerializationContext());
        self::assertSame($context, $clone->getSerializationContext());
    }
}

final class ContextUsageRecorder
{
    public ?SerializationContext $lastUsedContext = null;
}

final class CloningContextRecordingDataConverter implements DataConverterInterface, SerializationContextAwareInterface
{
    private ?SerializationContext $boundContext = null;

    public function __construct(
        private readonly ContextUsageRecorder $recorder,
    ) {}

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->boundContext = $context;
        return $clone;
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->boundContext;
    }

    public function fromPayload(Payload $payload, mixed $type): mixed
    {
        $this->recorder->lastUsedContext = $this->boundContext;
        return 'hello';
    }

    public function toPayload(mixed $value): Payload
    {
        $this->recorder->lastUsedContext = $this->boundContext;
        return new Payload();
    }
}

final class ContextRecordingDataConverter implements DataConverterInterface, SerializationContextAwareInterface
{
    public ?SerializationContext $boundContext = null;
    public ?SerializationContext $lastUsedContext = null;

    public function withSerializationContext(?SerializationContext $context): static
    {
        $this->boundContext = $context;
        return $this;
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->boundContext;
    }

    public function fromPayload(Payload $payload, mixed $type): mixed
    {
        $this->lastUsedContext = $this->boundContext;
        return 'hello';
    }

    public function toPayload(mixed $value): Payload
    {
        $this->lastUsedContext = $this->boundContext;
        return new Payload();
    }
}
