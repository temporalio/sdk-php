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

class ScalarJsonConverter implements PayloadConverterInterface
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
        return Payload::create(
            [EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_JSON],
            \json_encode($value, \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param Payload $payload
     * @param \ReflectionType|null $type
     * @return mixed|void
     */
    public function fromPayload(Payload $payload, ?\ReflectionType $type)
    {
        try {
            return \json_decode($payload->getData(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
