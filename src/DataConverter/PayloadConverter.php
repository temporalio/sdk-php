<?php

namespace Temporal\Client\DataConverter;

use Temporal\Client\Exception\DataConverterException;

interface PayloadConverter
{
    /**
     * Returns associated encoding type.
     *
     * @return string
     */
    public function getEncodingType(): string;

    /**
     * Implements conversion of a single value to Payload.
     *
     * @param mixed $value
     * @return Payload
     *
     * @throws DataConverterException
     */
    public function toData($value): Payload;

    /**
     * @param Payload $payload
     * @param \ReflectionParameter $type
     * @return mixed
     *
     * @throws DataConverterException
     */
    public function fromData(Payload $payload, \ReflectionParameter $type);
}
