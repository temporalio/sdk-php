<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\DataConverter;

use Temporal\DataConverter\Bytes;
use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\Payload;
use Temporal\DataConverter\PayloadConverterInterface;
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
     * @param \ReflectionType|null $type
     * @return Bytes
     */
    public function fromPayload(Payload $payload, ?\ReflectionType $type)
    {
        if ($type === null || !$type instanceof \ReflectionNamedType) {
            throw new DataConverterException('Unable to convert raw data to non Bytes type');
        }

        if ($type->getName() !== Bytes::class) {
            throw new DataConverterException('Unable to convert raw data to non Bytes type');
        }

        return new Bytes($payload->getData());
    }
}
