<?php

namespace Temporal\Client\DataConverter;

use Temporal\Client\Exception\DataConverterException;
use ReflectionType;

interface PayloadConverterInterface
{
    /**
     * Returns associated encoding type.
     *
     * @return string
     */
    public function getEncodingType(): string;

    /**
     * Implements conversion of a single value to Payload. Must return null if value can't be encoded.
     *
     * @param mixed $value
     * @return Payload|null
     *
     * @throws DataConverterException
     */
    public function toPayload($value): ?Payload;

    /**
     * @param Payload $payload
     * @param ReflectionType|null $type
     * @return mixed
     *
     * @throws DataConverterException
     */
    public function fromPayload(Payload $payload, ?ReflectionType $type);
}
