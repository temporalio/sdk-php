<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Client\Internal\DataConverter;

use Temporal\Client\DataConverter\DataConverterInterface;
use Temporal\Client\DataConverter\EncodingKeys;
use Temporal\Client\DataConverter\Payload;
use Temporal\Client\DataConverter\PayloadConverterInterface;
use Temporal\Client\Exception\DataConverterException;
use ReflectionType;

class DataConverter implements DataConverterInterface
{
    /**
     * @var array<PayloadConverterInterface>
     */
    private array $converters = [];

    /**
     * @param PayloadConverterInterface ...$converter
     */
    public function __construct(PayloadConverterInterface ...$converter)
    {
        foreach ($converter as $c) {
            $this->converters[$c->getEncodingType()] = $c;
        }
    }

    /**
     * @param array<Payload> $payloads
     * @param array<ReflectionType> $types
     * @return array
     */
    public function fromPayloads(array $payloads, array $types): array
    {
        $values = [];
        foreach ($payloads as $i => $payload) {
            $encoding = $payload->getMetadata()[EncodingKeys::METADATA_ENCODING_KEY];

            if (!isset($this->converters[$encoding])) {
                throw new DataConverterException(sprintf("Undefined payload encoding %s", $encoding));
            }

            $values[] = $this->converters[$encoding]->fromPayload($payload, $types[$i] ?? null);
        }

        return $values;
    }

    /**
     * @param array $values
     * @return array<Payload>
     */
    public function toPayloads(array $values): array
    {
        $payloads = [];
        foreach ($values as $value) {
            foreach ($this->converters as $converter) {
                $payload = $converter->toPayload($value);
                if ($payload !== null) {
                    $payloads[] = $payload;
                    continue 2;
                }
            }

            throw new DataConverterException(
                sprintf("Unable to convert value of type %s to Payload", gettype($value))
            );
        }

        return $payloads;
    }
}
