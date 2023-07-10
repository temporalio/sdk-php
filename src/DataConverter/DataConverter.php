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

final class DataConverter implements DataConverterInterface
{
    /**
     * @var array<PayloadConverterInterface>
     */
    private array $converters = [];

    /**
     * @param PayloadConverterInterface ...$converter
     */
    public function __construct(PayloadConverterInterface ...$converter)
    {
        foreach ($converter as $c) {
            $this->converters[$c->getEncodingType()] = $c;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function fromPayload(Payload $payload, $type)
    {
        /** @var \ArrayAccess $meta */
        $meta = $payload->getMetadata();

        $encoding = $meta[EncodingKeys::METADATA_ENCODING_KEY];

        if (!isset($this->converters[$encoding])) {
            throw new DataConverterException(sprintf('Undefined payload encoding %s', $encoding));
        }

        $type = Type::create($type);
        if (\in_array($type->getName(),  [Type::TYPE_VOID, Type::TYPE_NULL, Type::TYPE_FALSE, Type::TYPE_TRUE], true)) {
            return match($type->getName()) {
                Type::TYPE_VOID, Type::TYPE_NULL => null,
                Type::TYPE_TRUE => true,
                Type::TYPE_FALSE => false,
            };
        }

        return $this->converters[$encoding]->fromPayload($payload, $type);
    }

    /**
     * @param mixed $value
     * @return Payload
     *
     * @throws DataConverterException
     */
    public function toPayload($value): Payload
    {
        foreach ($this->converters as $converter) {
            $payload = $converter->toPayload($value);

            if ($payload !== null) {
                return $payload;
            }
        }

        throw new DataConverterException(
            \sprintf('Unable to convert value of type %s to Payload', \get_debug_type($value))
        );
    }

    /**
     * @return DataConverterInterface
     */
    public static function createDefault(): DataConverterInterface
    {
        return new DataConverter(
            new NullConverter(),
            new BinaryConverter(),
            new ProtoJsonConverter(),
            new JsonConverter()
        );
    }
}
