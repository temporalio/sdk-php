<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Temporal\Client\Internal\DataConverter;

use Temporal\Client\DataConverter\EncodingKeys;
use Temporal\Client\DataConverter\Payload;
use Temporal\Client\DataConverter\PayloadConverterInterface;
use ReflectionType;
use Temporal\Client\Exception\DataConverterException;
use Temporal\Client\Internal\Marshaller\MarshallerInterface;

class JsonConverter implements PayloadConverterInterface
{
    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param MarshallerInterface $marshaller
     */
    public function __construct(MarshallerInterface $marshaller)
    {
        $this->marshaller = $marshaller;
    }

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
        if (is_object($value)) {
            $value = $this->marshaller->marshal($value);
        }

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
            $data = json_decode($payload->getData(), true, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }

        if (
            $type !== null
            && is_array($data)
            && $type instanceof \ReflectionNamedType
            && class_exists($type->getName())
        ) {
            $obj = new \ReflectionClass($type->getName());

            return $this->marshaller->unmarshal($data, $obj->newInstanceWithoutConstructor());
        }

        return $data;
    }
}
