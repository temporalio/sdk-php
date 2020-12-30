<?php

namespace Temporal\Client\Internal\DataConverter;

use Temporal\Client\DataConverter\EncodingKeys;
use Temporal\Client\DataConverter\Payload;
use Temporal\Client\DataConverter\PayloadConverterInterface;
use Temporal\Client\Exception\DataConverterException;
use ReflectionType;

class ScalarJsonConverter implements PayloadConverterInterface
{
    /**
     * @return string
     */
    public function getEncodingType(): string
    {
        return EncodingKeys::METADATA_ENCODING_JSON;
    }

    /**
     * @param mixed $value
     * @return Payload|null
     */
    public function toPayload($value): ?Payload
    {
        return Payload::create(
            [EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_JSON],
            json_encode($value)
        );
    }

    /**
     * @param Payload $payload
     * @param ReflectionType|null $type
     * @return mixed|void
     */
    public function fromPayload(Payload $payload, ?ReflectionType $type)
    {
        try {
            return json_decode($payload->getData(), true, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
