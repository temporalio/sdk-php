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
use Temporal\DataConverter\DataConverterInterface;
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
        $context = new WorkflowSerializationContext('default', 'wf-1');

        $payloads = EncodedValues::fromValues(['payload'], self::signingConverter($context))->toPayloads();

        $decoded = EncodedValues::fromPayloads($payloads, self::signingConverter($context));

        self::assertSame('payload', $decoded->getValue(0, Type::TYPE_STRING));
    }

    public function testWorkflowContextSignatureMismatchFailsDecode(): void
    {
        $encodeContext = new WorkflowSerializationContext('default', 'wf-A');
        $decodeContext = new WorkflowSerializationContext('default', 'wf-B');

        $payloads = EncodedValues::fromValues(['payload'], self::signingConverter($encodeContext))->toPayloads();

        $decoded = EncodedValues::fromPayloads($payloads, self::signingConverter($decodeContext));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signature mismatch: expected "wf-B", got "wf-A"');
        $decoded->getValue(0, Type::TYPE_STRING);
    }

    public function testActivityContextSignatureMismatchFailsDecode(): void
    {
        $encodeContext = new ActivitySerializationContext(
            namespace: 'default',
            workflowId: 'wf-1',
            activityType: 'Charge',
            taskQueue: 'tq',
        );
        $decodeContext = new ActivitySerializationContext(
            namespace: 'default',
            workflowId: 'wf-1',
            activityType: 'Refund',
            taskQueue: 'tq',
        );

        $payloads = EncodedValues::fromValues(['payload'], self::signingConverter($encodeContext))->toPayloads();

        $decoded = EncodedValues::fromPayloads($payloads, self::signingConverter($decodeContext));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signature mismatch: expected "wf-1:Refund", got "wf-1:Charge"');
        $decoded->getValue(0, Type::TYPE_STRING);
    }

    public function testStandaloneActivityContextAllowsNullWorkflowFields(): void
    {
        $context = new ActivitySerializationContext(namespace: 'default', activityType: 'Charge', taskQueue: 'tq');

        self::assertNull($context->getWorkflowId());
        self::assertNull($context->workflowType);

        $payloads = EncodedValues::fromValues(['payload'], self::signingConverter($context))->toPayloads();

        $decoded = EncodedValues::fromPayloads(
            $payloads,
            self::signingConverter(
                new ActivitySerializationContext(namespace: 'default', activityType: 'Charge', taskQueue: 'tq'),
            ),
        );

        self::assertSame('payload', $decoded->getValue(0, Type::TYPE_STRING));
    }

    private static function signingConverter(SerializationContext $context): DataConverterInterface
    {
        return (new DataConverter(new SigningPayloadConverter()))->withSerializationContext($context);
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

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->context;
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
            ->setMetadata(['encoding' => self::ENCODING, 'signature' => self::signatureOf($this->context)])
            ->setData($value);
    }

    public function fromPayload(Payload $payload, Type $type): mixed
    {
        $metadata = $payload->getMetadata();
        $actual = isset($metadata['signature']) ? $metadata['signature'] : '';
        $expected = self::signatureOf($this->context);

        if ($actual !== $expected) {
            throw new \RuntimeException(
                \sprintf('Signature mismatch: expected "%s", got "%s"', $expected, $actual),
            );
        }

        return $payload->getData();
    }

    private static function signatureOf(?SerializationContext $context): string
    {
        return match (true) {
            $context instanceof ActivitySerializationContext =>
                (string) $context->workflowId . ':' . (string) $context->activityType,
            $context instanceof HasWorkflowSerializationContext => (string) $context->getWorkflowId(),
            default => '',
        };
    }
}
