<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payload;
use Temporal\Exception\DataConverterException;

/**
 * @psalm-import-type TType from Type
 */
final class DataConverter implements DataConverterInterface, SerializationContextAwareInterface
{
    /**
     * @var array<PayloadConverterInterface>
     */
    private array $converters = [];

    private ?SerializationContext $serializationContext = null;

    public function __construct(PayloadConverterInterface ...$converter)
    {
        foreach ($converter as $c) {
            $this->converters[$c->getEncodingType()] = $c;
        }
    }

    public static function createDefault(): DataConverterInterface
    {
        return new DataConverter(
            new NullConverter(),
            new BinaryConverter(),
            new ProtoJsonConverter(),
            new ProtoConverter(),
            new JsonConverter(),
        );
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->serializationContext;
    }

    public function withSerializationContext(?SerializationContext $context): static
    {
        if ($context === $this->serializationContext) {
            return $this;
        }

        $clone = clone $this;
        $clone->serializationContext = $context;

        foreach ($this->converters as $encoding => $converter) {
            if ($converter instanceof SerializationContextAwareInterface) {
                $clone->converters[$encoding] = $converter->withSerializationContext($context);
            }
        }

        return $clone;
    }

    /**
     * @param TType $type
     */
    public function fromPayload(Payload $payload, $type): mixed
    {
        $type = Type::create($type);

        if ($type->isClass() && $type->getName() === RawValue::class) {
            return new RawValue($payload);
        }

        /** @var \ArrayAccess $meta */
        $meta = $payload->getMetadata();

        $encoding = $meta[EncodingKeys::METADATA_ENCODING_KEY];

        if (!isset($this->converters[$encoding])) {
            throw new DataConverterException(\sprintf('Undefined payload encoding "%s"', $encoding));
        }

        return match ($type->getName()) {
            Type::TYPE_VOID,
            Type::TYPE_NULL => null,
            Type::TYPE_TRUE => true,
            Type::TYPE_FALSE => false,
            default => $this->converters[$encoding]->fromPayload($payload, $type),
        };
    }

    /**
     * @param mixed $value
     *
     * @throws DataConverterException
     */
    public function toPayload($value): Payload
    {
        if ($value instanceof RawValue) {
            return $value->getPayload();
        }
        foreach ($this->converters as $converter) {
            $payload = $converter->toPayload($value);

            if ($payload !== null) {
                return $payload;
            }
        }

        throw new DataConverterException(
            \sprintf('Unable to convert value of type %s to Payload', \get_debug_type($value)),
        );
    }
}
