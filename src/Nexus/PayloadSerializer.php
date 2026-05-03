<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Serializer\Internal\Content;
use Temporal\Nexus\Serializer\Internal\SerializerInterface;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverterInterface;

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

        // Protobuf MapField: psalm sees only ArrayAccess, narrow to plain array.
        $headers = \iterator_to_array($payload->getMetadata());

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
