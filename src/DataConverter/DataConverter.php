<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Exception\DataConverterException;

class DataConverter implements DataConverterInterface
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
     * @param array<Payload> $payloads
     * @param array<\ReflectionType> $types
     * @return array
     */
    public function fromPayloads(array $payloads, array $types): array
    {
        $values = [];
        foreach ($payloads as $i => $payload) {
            $values[] = $this->fromPayload($payload, $types[$i] ?? null);
        }

        return $values;
    }

    /**
     * @param Payload $payload
     * @param \ReflectionType|null $type
     * @return mixed
     */
    public function fromPayload(Payload $payload, ?\ReflectionType $type)
    {
        $encoding = $payload->getMetadata()[EncodingKeys::METADATA_ENCODING_KEY];

        if (!isset($this->converters[$encoding])) {
            throw new DataConverterException(sprintf('Undefined payload encoding %s', $encoding));
        }

        return $this->converters[$encoding]->fromPayload($payload, $type);
    }

    /**
     * @param array $values
     * @return array<Payload>
     */
    public function toPayloads(array $values): array
    {
        $payloads = [];

        foreach ($values as $value) {
            foreach ($this->converters as $converter) {
                $payload = $converter->toPayload($value);

                if ($payload !== null) {
                    $payloads[] = $payload;
                    continue 2;
                }
            }

            throw new DataConverterException(
                \sprintf('Unable to convert value of type %s to Payload', \get_debug_type($value))
            );
        }

        return $payloads;
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
            new ProtoConverter(),
            new JsonDTOConverter()
        );
    }
}
