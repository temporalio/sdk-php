<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Tests\Unit\DataConverter\Stub\ContextStampingConverter;

#[CoversClass(EncodedValues::class)]
#[CoversClass(EncodedCollection::class)]
final class SerializationContextValuesTestCase extends AbstractUnit
{
    public function testContextIsAppliedOnEncode(): void
    {
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $values = EncodedValues::fromValues(['payload'], new ContextStampingConverter())
            ->withSerializationContext($context);

        $payload = $values->toPayloads()->getPayloads()[0];

        self::assertSame('wf-1', $payload->getMetadata()['ctx']);
    }

    public function testContextIsAppliedOnDecode(): void
    {
        $context = new WorkflowSerializationContext('default', 'wf-7');
        $log = new \ArrayObject();

        $values = EncodedValues::fromValues(['payload'], new ContextStampingConverter())
            ->withSerializationContext($context);
        $payloads = $values->toPayloads();

        $decoded = EncodedValues::fromPayloads($payloads, new ContextStampingConverter($log))
            ->withSerializationContext($context);
        $decoded->getValue(0, Type::TYPE_STRING);

        self::assertContains('from:wf-7', $log->getArrayCopy());
    }

    public function testWithSerializationContextIsImmutable(): void
    {
        $converter = new ContextStampingConverter();
        $original = EncodedValues::fromValues(['payload'], $converter);
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $derived = $original->withSerializationContext($context);

        self::assertNotSame($original, $derived);
        self::assertNull($original->getSerializationContext());
        self::assertSame($context, $derived->getSerializationContext());
    }

    public function testSetSerializationContextMutatesInPlace(): void
    {
        $context = new WorkflowSerializationContext('default', 'wf-1');
        $values = EncodedValues::fromValues(['payload'], new ContextStampingConverter());

        $values->setSerializationContext($context);

        self::assertSame($context, $values->getSerializationContext());
        self::assertSame('wf-1', $values->toPayloads()->getPayloads()[0]->getMetadata()['ctx']);
    }

    public function testNullContextDoesNotWrapConverter(): void
    {
        $log = new \ArrayObject();
        $values = EncodedValues::fromValues(['payload'], new ContextStampingConverter($log));

        $values->toPayloads();

        self::assertNotContains('wrap:', $log->getArrayCopy());
        self::assertSame([], \array_filter(
            $log->getArrayCopy(),
            static fn(string $entry): bool => \str_starts_with($entry, 'wrap:'),
        ));
    }

    public function testSetSerializationContextInvalidatesEffectiveConverter(): void
    {
        $values = EncodedValues::fromValues(['payload'], new ContextStampingConverter());

        self::assertSame('', $values->toPayloads()->getPayloads()[0]->getMetadata()['ctx']);

        $values->setSerializationContext(new WorkflowSerializationContext('default', 'wf-2'));

        self::assertSame('wf-2', $values->toPayloads()->getPayloads()[0]->getMetadata()['ctx']);
    }

    public function testEncodedCollectionCarriesContext(): void
    {
        $context = new WorkflowSerializationContext('default', 'wf-9');

        $collection = EncodedCollection::fromValues(['k' => 'payload'], new ContextStampingConverter())
            ->withSerializationContext($context);

        $payloads = $collection->toPayloadArray();

        self::assertSame('wf-9', $payloads['k']->getMetadata()['ctx']);
    }
}
