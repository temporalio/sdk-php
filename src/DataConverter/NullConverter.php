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

class NullConverter extends Converter
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

        return $this->create('');
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
