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
     * @param \ReflectionType|null $type
     * @return null
     */
    public function fromPayload(Payload $payload, ?\ReflectionType $type)
    {
        if ($type !== null && ! $type->allowsNull()) {
            throw new DataConverterException('Unable to convert null to non nullable type');
        }

        return null;
    }
}
