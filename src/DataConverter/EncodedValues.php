<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payloads;

class EncodedValues
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
            // todo: skip this step
            $payload = $this->dataConverter->toPayload($value);

            $pp = new \Temporal\Api\Common\V1\Payload();
            $pp->setMetadata($payload->getMetadata());
            $pp->setData($payload->getData());

            $data[] = $pp;
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
     * @param array $values
     * @param DataConverterInterface|null $dataConverter
     * @return EncodedValues
     */
    public static function createFromValues(array $values, DataConverterInterface $dataConverter = null): EncodedValues
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
    public static function createFromPayloads(Payloads $payloads, DataConverterInterface $dataConverter): EncodedValues
    {
        $ev = new self();
        $ev->payloads = $payloads;
        $ev->dataConverter = $dataConverter;

        return $ev;
    }

    // todo: create sliced
}
