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

        EncodedValues::fromValues(['hello'], $converter)
            ->withSerializationContext($context)
            ->toPayloads();

        self::assertSame($context, $converter->lastUsedContext);
    }

    public function testGetValueAppliesBoundContextAtConversionTime(): void
    {
        $converter = new ContextRecordingDataConverter();
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $payloads = EncodedValues::fromValues(['hello'], $converter)->toPayloads();
        EncodedValues::fromPayloads($payloads, $converter)
            ->withSerializationContext($context)
            ->getValue(0);

        self::assertSame($context, $converter->lastUsedContext);
    }

    public function testNumericLookingWorkflowIdsAreDifferentContexts(): void
    {
        $converter = new ContextRecordingDataConverter();
        $rebound = new WorkflowSerializationContext('default', '1e2');

        EncodedValues::fromValues(['hello'], $converter)
            ->withSerializationContext(new WorkflowSerializationContext('default', '100'))
            ->withSerializationContext($rebound)
            ->toPayloads();

        self::assertSame($rebound, $converter->lastUsedContext);
    }

    public function testEqualContextKeepsSameInstance(): void
    {
        $values = EncodedValues::fromValues(['hello'])
            ->withSerializationContext(new WorkflowSerializationContext('default', 'wf-1'));

        self::assertSame($values, $values->withSerializationContext(new WorkflowSerializationContext('default', 'wf-1')));
    }

    public function testRebindingLeavesOriginalInstanceUnchanged(): void
    {
        $original = EncodedValues::fromValues(['hello']);

        $original->withSerializationContext(new WorkflowSerializationContext('default', 'wf-1'));

        self::assertNull($original->getSerializationContext());
    }

    public function testWithoutContextConverterSeesNull(): void
    {
        $converter = new ContextRecordingDataConverter();

        $values = EncodedValues::fromValues(['hello'], $converter);
        $values->toPayloads();

        self::assertNull($converter->lastUsedContext);
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
