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

class BinaryConverter implements PayloadConverterInterface
{
    /**
     * @return string
     */
    public function getEncodingType(): string
    {
        return EncodingKeys::METADATA_ENCODING_RAW;
    }

    /**
     * @param mixed $value
     * @return Payload|null
     */
    public function toPayload($value): ?Payload
    {
        if (!$value instanceof Bytes) {
            return null;
        }

        return Payload::create(
            [EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_RAW],
            $value->getData()
        );
    }

    /**
     * @param Payload $payload
     * @param Type $type
     * @return Bytes
     */
    public function fromPayload(Payload $payload, Type $type)
    {
        if (!$type->isClass()) {
            throw new DataConverterException('Unable to convert raw data to non Bytes type');
        }

        if ($type->getName() !== Bytes::class) {
            throw new DataConverterException('Unable to convert raw data to non Bytes type');
        }

        return new Bytes($payload->getData());
    }
}
