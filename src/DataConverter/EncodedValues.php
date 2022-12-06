<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use React\Promise\PromiseInterface;
use Temporal\Api\Common\V1\Payloads;

class EncodedValues extends EncodedPayloads implements ValuesInterface
{
    /**
     * @param Payloads $payloads
     * @param DataConverterInterface $dataConverter
     * @return EncodedValues
     */
    public static function fromPayloads(Payloads $payloads, DataConverterInterface $dataConverter): EncodedValues
    {
        return parent::fromPayloadCollection($payloads->getPayloads(), $dataConverter);
    }

    /**
     * @return Payloads
     */
    public function toPayloads(): Payloads
    {
        $payloads = new Payloads();
        $payloads->setPayloads($this->toProtoCollection());
        return $payloads;
    }

    /**
     * @param DataConverterInterface $converter
     * @param ValuesInterface $values
     * @param int $offset
     * @param int|null $length
     * @return ValuesInterface
     */
    public static function sliceValues(
        DataConverterInterface $converter,
        ValuesInterface $values,
        int $offset,
        int $length = null
    ): ValuesInterface {
        $payloads = $values->toPayloads();
        $newPayloads = new Payloads();
        $newPayloads->setPayloads(array_slice(iterator_to_array($payloads->getPayloads()), $offset, $length));

        return self::fromPayloads($newPayloads, $converter);
    }

    /**
     * Decode promise response upon returning it to the domain layer.
     *
     * @param PromiseInterface $promise
     * @param Type|string|null $type
     * @return PromiseInterface
     */
    public static function decodePromise(PromiseInterface $promise, $type = null): PromiseInterface
    {
        return $promise->then(
            function ($value) use ($type) {
                if (!$value instanceof ValuesInterface || $value instanceof \Throwable) {
                    return $value;
                }

                return $value->getValue(0, $type);
            }
        );
    }
}
