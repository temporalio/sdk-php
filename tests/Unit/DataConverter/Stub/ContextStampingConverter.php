<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter\Stub;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\HasWorkflowSerializationContext;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;

final class ContextStampingConverter implements DataConverterInterface, SerializationContextAwareInterface
{
    public function __construct(
        public readonly \ArrayObject $log = new \ArrayObject(),
        private ?SerializationContext $context = null,
    ) {}

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->context;
    }

    public function withSerializationContext(?SerializationContext $context): static
    {
        $this->log->append('wrap:' . self::stampOf($context));

        $clone = clone $this;
        $clone->context = $context;

        return $clone;
    }

    public function fromPayload(Payload $payload, mixed $type): mixed
    {
        $this->log->append('from:' . self::stampOf($this->context));

        return $payload->getData();
    }

    public function toPayload(mixed $value): Payload
    {
        $this->log->append('to:' . self::stampOf($this->context));

        return (new Payload())
            ->setMetadata(['encoding' => 'test/ctx', 'ctx' => self::stampOf($this->context)])
            ->setData((string) $value);
    }

    /**
     * @return list<string>
     */
    public function wraps(): array
    {
        return self::entries($this->log, 'wrap:');
    }

    /**
     * @return list<string>
     */
    public function reads(): array
    {
        return self::entries($this->log, 'from:');
    }

    private static function stampOf(?SerializationContext $context): string
    {
        return $context instanceof HasWorkflowSerializationContext
            ? (string) $context->getWorkflowId()
            : '';
    }

    /**
     * @return list<string>
     */
    private static function entries(\ArrayObject $log, string $prefix): array
    {
        return \array_values(\array_filter(
            $log->getArrayCopy(),
            static fn(string $entry): bool => \str_starts_with($entry, $prefix),
        ));
    }
}
