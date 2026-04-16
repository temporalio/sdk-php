<?php

declare(strict_types=1);

namespace Temporal\Nexus;

use Nexus\Sdk\Serializer\Content;
use Nexus\Sdk\Serializer\SerializerInterface;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodingKeys;

/**
 * Bridges Nexus SDK serialization to Temporal DataConverter.
 */
final class PayloadSerializer implements SerializerInterface
{
    public function __construct(
        private readonly DataConverterInterface $dataConverter,
    ) {}

    public function getDataConverter(): DataConverterInterface
    {
        return $this->dataConverter;
    }

    public function serialize(mixed $value): Content
    {
        $payload = $this->dataConverter->toPayload($value);
        $data = $payload->getData();

        $headers = [];
        /** @var \ArrayAccess $meta */
        $meta = $payload->getMetadata();
        foreach ($meta as $key => $val) {
            $headers[$key] = $val;
        }

        return new Content($data, $headers);
    }

    public function deserialize(Content $content, string $type): mixed
    {
        $payload = new Payload();
        $payload->setData($content->data);

        $metadata = [];
        foreach ($content->headers as $key => $value) {
            $metadata[$key] = $value;
        }

        if ($metadata !== []) {
            $payload->setMetadata($metadata);
        }

        if ($type === 'void') {
            return null;
        }

        return $this->dataConverter->fromPayload($payload, $type);
    }
}
