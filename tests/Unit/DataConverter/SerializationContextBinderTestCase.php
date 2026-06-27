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
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\SerializationContextBinder;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(SerializationContextBinder::class)]
final class SerializationContextBinderTestCase extends AbstractUnit
{
    public function testNullContextReturnsSameConverter(): void
    {
        $converter = DataConverter::createDefault();

        self::assertSame($converter, SerializationContextBinder::bind($converter, null));
    }

    public function testNonAwareConverterReturnsSameInstance(): void
    {
        $converter = new class implements DataConverterInterface {
            public function fromPayload(Payload $payload, mixed $type): mixed
            {
                return null;
            }

            public function toPayload(mixed $value): Payload
            {
                return new Payload();
            }
        };

        $context = new WorkflowSerializationContext('default', 'wf-1');

        self::assertSame($converter, SerializationContextBinder::bind($converter, $context));
    }

    public function testAwareConverterReturnsResultOfWithSerializationContext(): void
    {
        $context = new WorkflowSerializationContext('default', 'wf-1');
        $converter = new RecordingAwareDataConverter();

        $result = SerializationContextBinder::bind($converter, $context);

        self::assertNotSame($converter, $result);
        self::assertInstanceOf(RecordingAwareDataConverter::class, $result);
        self::assertSame($context, $result->boundContext);
        self::assertNull($converter->boundContext);
    }
}

final class RecordingAwareDataConverter implements DataConverterInterface, SerializationContextAwareInterface
{
    public ?SerializationContext $boundContext = null;

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
        return null;
    }

    public function toPayload(mixed $value): Payload
    {
        return new Payload();
    }
}
