<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payload;
use Temporal\Exception\DataConverterException;

class RawValueConverter extends Converter
{
    public function getEncodingType(): string
    {
        return EncodingKeys::METADATA_ENCODING_RAW_VALUE;
    }

    public function toPayload(mixed $value): ?Payload
    {
        if (!$value instanceof RawValue) {
            return null;
        }

        $payload = $value->getPayload();
        $payload->setMetadata([EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_RAW_VALUE]);

        return $payload;
    }

    public function fromPayload(Payload $payload, Type $type): RawValue
    {
        return new RawValue(clone $payload);
    }
}
