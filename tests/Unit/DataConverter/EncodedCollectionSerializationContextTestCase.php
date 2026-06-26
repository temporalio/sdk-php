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
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\HasWorkflowSerializationContext;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(EncodedCollection::class)]
final class EncodedCollectionSerializationContextTestCase extends AbstractUnit
{
    public function testRoundTripWithMatchingContext(): void
    {
        $converter = new DataConverter(new CollectionSigningConverter());
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $encoded = EncodedCollection::fromValues(['note' => 'remember'], $converter);
        $encoded->setSerializationContext($context);
        $payloads = $encoded->toPayloadArray();

        $decoded = EncodedCollection::fromPayloadCollection($payloads, $converter);
        $decoded->setSerializationContext(new WorkflowSerializationContext('default', 'wf-1'));

        self::assertSame('remember', $decoded->getValue('note', Type::TYPE_STRING));
    }

    public function testMismatchedContextFailsDecode(): void
    {
        $converter = new DataConverter(new CollectionSigningConverter());

        $encoded = EncodedCollection::fromValues(['note' => 'remember'], $converter);
        $encoded->setSerializationContext(new WorkflowSerializationContext('default', 'wf-A'));
        $payloads = $encoded->toPayloadArray();

        $decoded = EncodedCollection::fromPayloadCollection($payloads, $converter);
        $decoded->setSerializationContext(new WorkflowSerializationContext('default', 'wf-B'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signature mismatch: expected "wf-B", got "wf-A"');
        $decoded->getValue('note', Type::TYPE_STRING);
    }

    public function testChangingContextRebindsConverter(): void
    {
        $converter = new DataConverter(new CollectionSigningConverter());

        $collection = EncodedCollection::fromValues(['note' => 'remember'], $converter);

        $collection->setSerializationContext(new WorkflowSerializationContext('default', 'wf-A'));
        $first = $collection->toPayloadArray();
        self::assertSame('wf-A', $first['note']->getMetadata()['signature']);

        $collection->setSerializationContext(new WorkflowSerializationContext('default', 'wf-B'));
        $second = $collection->toPayloadArray();
        self::assertSame('wf-B', $second['note']->getMetadata()['signature']);
    }

    public function testWithoutContextEncodesWithoutSignature(): void
    {
        $converter = new DataConverter(new CollectionSigningConverter());

        $collection = EncodedCollection::fromValues(['note' => 'remember'], $converter);
        $payloads = $collection->toPayloadArray();

        self::assertSame('', $payloads['note']->getMetadata()['signature']);
    }

    public function testWithValueCloneResetsBoundConverter(): void
    {
        $converter = new DataConverter(new CollectionSigningConverter());

        $original = EncodedCollection::fromValues(['note' => 'remember'], $converter);
        $original->setSerializationContext(new WorkflowSerializationContext('default', 'wf-A'));
        self::assertSame('wf-A', $original->toPayloadArray()['note']->getMetadata()['signature']);

        $clone = $original->withValue('extra', 'value');
        $clone->setSerializationContext(new WorkflowSerializationContext('default', 'wf-B'));

        self::assertSame('wf-B', $clone->toPayloadArray()['note']->getMetadata()['signature']);
        self::assertSame('wf-A', $original->toPayloadArray()['note']->getMetadata()['signature']);
    }
}

final class CollectionSigningConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    private const ENCODING = 'signed-test';

    private ?SerializationContext $context = null;

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->context = $context;
        return $clone;
    }

    public function getEncodingType(): string
    {
        return self::ENCODING;
    }

    public function toPayload($value): ?Payload
    {
        if (!\is_string($value)) {
            return null;
        }

        return (new Payload())
            ->setMetadata(['encoding' => self::ENCODING, 'signature' => $this->signature()])
            ->setData($value);
    }

    public function fromPayload(Payload $payload, Type $type): mixed
    {
        $metadata = $payload->getMetadata();
        $actual = isset($metadata['signature']) ? $metadata['signature'] : '';
        $expected = $this->signature();

        if ($actual !== $expected) {
            throw new \RuntimeException(
                \sprintf('Signature mismatch: expected "%s", got "%s"', $expected, $actual),
            );
        }

        return $payload->getData();
    }

    private function signature(): string
    {
        return $this->context instanceof HasWorkflowSerializationContext
            ? (string) $this->context->getWorkflowId()
            : '';
    }
}
