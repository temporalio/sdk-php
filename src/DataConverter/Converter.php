<?php


namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payload;

abstract class Converter implements PayloadConverterInterface
{
    /**
     * @param string $data
     * @return Payload
     */
    protected function create(string $data): Payload
    {
        $payload = new Payload();
        $payload->setMetadata([EncodingKeys::METADATA_ENCODING_KEY => $this->getEncodingType()]);
        $payload->setData($data);

        return $payload;
    }
}
