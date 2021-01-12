<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payloads;
use Temporal\Exception\DataConverterException;

class EncodedValues implements ValuesInterface
{
    /**
     * @var DataConverterInterface|null
     */
    private ?DataConverterInterface $dataConverter = null;

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
    public function getSize(): int
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
        return $this->getSize() === 0;
    }

    /**
     * @param int $index
     * @param Type|string|null $type
     * @return mixed
     */
    public function getValue(int $index, $type = null)
    {
        // todo: optimize

        if (isset($this->values[$index])) {
            return $this->values[$index];
        }

        if ($this->dataConverter === null) {
            throw new \LogicException("DataConverter is not set");
        }

        /** @var \Temporal\Api\Common\V1\Payload $payload */
        $payload = $this->payloads->getPayloads()->offsetGet($index);

        $meta = [];

        // todo: remove it
        foreach ($payload->getMetadata() as $k => $v) {
            $meta[$k] = $v;
        }

        // todo: remove internal type of payload
        $internalPayload = Payload::create($meta, $payload->getData());

        return $this->dataConverter->fromPayload($internalPayload, $type);
    }

    /**
     * @return Payloads
     */
    public function toPayloads(): Payloads
    {
        if ($this->payloads !== null) {
            return $this->payloads;
        }

        if ($this->dataConverter === null) {
            throw new \LogicException("DataConverter is not set");
        }

        $data = [];
        foreach ($this->values as $value) {
            $data[] = $this->dataConverter->toPayload($value);
        }

        $payloads = new Payloads();
        $payloads->setPayloads($data);

        return $payloads;
    }

    // todo: get value by index

    /**
     * @param DataConverterInterface $dataConverter
     */
    public function setDataConverter(DataConverterInterface $dataConverter)
    {
        $this->dataConverter = $dataConverter;
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
        $ev->values = $values;
        $ev->dataConverter = $dataConverter;

        return $ev;
    }

    /**
     * @param Payloads $payloads
     * @param DataConverterInterface|null $dataConverter
     * @return EncodedValues
     */
    public static function fromPayloads(Payloads $payloads, DataConverterInterface $dataConverter): EncodedValues
    {
        $ev = new self();
        $ev->payloads = $payloads;
        $ev->dataConverter = $dataConverter;

        return $ev;
    }
}
