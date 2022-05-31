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

class EncodedValues implements ValuesInterface
{
    /**
     * @var DataConverterInterface|null
     */
    private ?DataConverterInterface $converter = null;

    /**
     * @var Payloads|null
     */
    private ?Payloads $payloads = null;

    /**
     * @var array|null
     */
    private ?array $values = null;

    /**
     * Can not be constructed directly.
     */
    private function __construct()
    {
    }

    /**
     * @return int
     */
    public function count(): int
    {
        if ($this->values !== null) {
            return count($this->values);
        }

        if ($this->payloads !== null) {
            return $this->payloads->getPayloads()->count();
        }

        return 0;
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @param int $index
     * @param Type|string|null $type
     * @return mixed
     */
    public function getValue(int $index, $type = null)
    {
        if (is_array($this->values) && array_key_exists($index, $this->values)) {
            return $this->values[$index];
        }

        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set');
        }

        /** @var \ArrayAccess $payloads */
        $payloads = $this->payloads->getPayloads();

        return $this->converter->fromPayload($payloads[$index], $type);
    }

    /**
     * @return Payloads
     */
    public function toPayloads(): Payloads
    {
        if ($this->payloads !== null) {
            return $this->payloads;
        }

        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set');
        }

        $data = [];
        foreach ($this->values as $value) {
            $data[] = $this->converter->toPayload($value);
        }

        $payloads = new Payloads();
        $payloads->setPayloads($data);

        return $payloads;
    }

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->converter = $converter;
    }

    /**
     * @return EncodedValues
     */
    public static function empty(): EncodedValues
    {
        $ev = new self();
        $ev->values = [];

        return $ev;
    }

    /**
     * @param array $values
     * @param DataConverterInterface|null $dataConverter
     * @return EncodedValues
     */
    public static function fromValues(array $values, DataConverterInterface $dataConverter = null): EncodedValues
    {
        $ev = new self();
        $ev->values = array_values($values);
        $ev->converter = $dataConverter;

        return $ev;
    }

    /**
     * @param Payloads $payloads
     * @param DataConverterInterface $dataConverter
     * @return EncodedValues
     */
    public static function fromPayloads(Payloads $payloads, DataConverterInterface $dataConverter): EncodedValues
    {
        $ev = new self();
        $ev->payloads = $payloads;
        $ev->converter = $dataConverter;

        return $ev;
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
}
