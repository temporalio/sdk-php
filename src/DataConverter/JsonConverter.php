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

class JsonConverter extends Converter
{
    /**
     * @return string
     */
    public function getEncodingType(): string
    {
        return EncodingKeys::METADATA_ENCODING_JSON;
    }

    /**
     * @param mixed $value
     * @return Payload|null
     * @throws \JsonException
     */
    public function toPayload($value): ?Payload
    {
        try {
            return self::create(\json_encode($value, \JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param Payload $payload
     * @param Type $type
     * @return mixed|void
     */
    public function fromPayload(Payload $payload, Type $type)
    {
        try {
            return \json_decode($payload->getData(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
