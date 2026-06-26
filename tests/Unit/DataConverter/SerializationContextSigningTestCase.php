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
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\HasWorkflowSerializationContext;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(EncodedValues::class)]
#[CoversClass(DataConverter::class)]
final class SerializationContextSigningTestCase extends AbstractUnit
{
    public function testWorkflowContextRoundTripWithMatchingContext(): void
    {
        $converter = new DataConverter(new SigningPayloadConverter());

        $encoded = EncodedValues::fromValues(['payload'], $converter);
        $encoded->setSerializationContext(new WorkflowSerializationContext('default', 'wf-1'));
        $payloads = $encoded->toPayloads();

        $decoded = EncodedValues::fromPayloads($payloads, $converter);
        $decoded->setSerializationContext(new WorkflowSerializationContext('default', 'wf-1'));

        self::assertSame('payload', $decoded->getValue(0, Type::TYPE_STRING));
    }

    public function testWorkflowContextSignatureMismatchFailsDecode(): void
    {
        $converter = new DataConverter(new SigningPayloadConverter());

        $encoded = EncodedValues::fromValues(['payload'], $converter);
        $encoded->setSerializationContext(new WorkflowSerializationContext('default', 'wf-A'));
        $payloads = $encoded->toPayloads();

        $decoded = EncodedValues::fromPayloads($payloads, $converter);
        $decoded->setSerializationContext(new WorkflowSerializationContext('default', 'wf-B'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signature mismatch: expected "wf-B", got "wf-A"');
        $decoded->getValue(0, Type::TYPE_STRING);
    }

    public function testActivityContextSignatureMismatchFailsDecode(): void
    {
        $converter = new DataConverter(new SigningPayloadConverter());

        $encoded = EncodedValues::fromValues(['payload'], $converter);
        $encoded->setSerializationContext(new ActivitySerializationContext(
            namespace: 'default',
            workflowId: 'wf-1',
            activityType: 'Charge',
        ));
        $payloads = $encoded->toPayloads();

        $decoded = EncodedValues::fromPayloads($payloads, $converter);
        $decoded->setSerializationContext(new ActivitySerializationContext(
            namespace: 'default',
            workflowId: 'wf-1',
            activityType: 'Refund',
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signature mismatch: expected "wf-1:Refund", got "wf-1:Charge"');
        $decoded->getValue(0, Type::TYPE_STRING);
    }

    public function testStandaloneActivityContextAllowsNullWorkflowFields(): void
    {
        $context = new ActivitySerializationContext(namespace: 'default', activityType: 'Charge');

        self::assertNull($context->getWorkflowId());
        self::assertNull($context->workflowType);

        $converter = new DataConverter(new SigningPayloadConverter());

        $encoded = EncodedValues::fromValues(['payload'], $converter);
        $encoded->setSerializationContext($context);
        $payloads = $encoded->toPayloads();

        $decoded = EncodedValues::fromPayloads($payloads, $converter);
        $decoded->setSerializationContext(new ActivitySerializationContext(namespace: 'default', activityType: 'Charge'));

        self::assertSame('payload', $decoded->getValue(0, Type::TYPE_STRING));
    }
}

final class SigningPayloadConverter implements PayloadConverterInterface, SerializationContextAwareInterface
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
        $context = $this->context;

        if ($context instanceof ActivitySerializationContext) {
            return (string) $context->workflowId . ':' . (string) $context->activityType;
        }

        if ($context instanceof HasWorkflowSerializationContext) {
            return (string) $context->getWorkflowId();
        }

        return '';
    }
}
