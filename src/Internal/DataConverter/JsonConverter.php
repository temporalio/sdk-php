<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\DataConverter;

use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\Payload;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\Exception\DataConverterException;
use Temporal\Internal\Marshaller\MarshallerInterface;

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
     * @throws \JsonException
     */
    public function toPayload($value): ?Payload
    {
        if (\is_object($value)) {
            $value = $this->marshaller->marshal($value);
        }

        return Payload::create(
            [EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_JSON],
            \json_encode($value, \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param Payload $payload
     * @param \ReflectionType|null $type
     * @return mixed|void
     * @throws \ReflectionException
     */
    public function fromPayload(Payload $payload, ?\ReflectionType $type)
    {
        try {
            $data = \json_decode($payload->getData(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }

        if (\is_array($data) && $type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
            $obj = new \ReflectionClass($type->getName());

            return $this->marshaller->unmarshal($data, $obj->newInstanceWithoutConstructor());
        }

        return $data;
    }
}
