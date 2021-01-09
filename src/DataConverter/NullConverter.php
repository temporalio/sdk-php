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

class NullConverter implements PayloadConverterInterface
{
    /**
     * @return string
     */
    public function getEncodingType(): string
    {
        return EncodingKeys::METADATA_ENCODING_NULL;
    }

    /**
     * @param mixed $value
     * @return Payload|null
     */
    public function toPayload($value): ?Payload
    {
        if ($value !== null) {
            return null;
        }

        return Payload::create(
            [EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_NULL],
            ''
        );
    }

    /**
     * @param Payload $payload
     * @param Type $type
     * @return null
     */
    public function fromPayload(Payload $payload, Type $type)
    {
        if (!$type->isUntyped() && !$type->allowsNull()) {
            throw new DataConverterException('Unable to convert null to non nullable type');
        }

        return null;
    }
}
