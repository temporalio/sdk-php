<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payload;

class EncodedCollection
{
    /**
     * @var DataConverterInterface|null
     */
    private ?DataConverterInterface $converter = null;

    /**
     * @var Payload[]
     */
    private ?array $payloads = null;

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
            return \count($this->values);
        }

        if ($this->payloads !== null) {
            return \count($this->payloads);
        }

        return 0;
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @param array-key $name
     * @param Type|string|null $type
     *
     * @return mixed
     */
    public function getValue(int|string $name, $type = null): mixed
    {
        if (\is_array($this->values) && \array_key_exists($name, $this->values)) {
            return $this->values[$name];
        }

        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set.');
        }

        return $this->converter->fromPayload($this->payloads[$name], $type);
    }

    public function getValues(): array
    {
        if (\is_array($this->values)) {
            return $this->values;
        }

        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set.');
        }

        if ($this->payloads === null) {
            return [];
        }

        $data = [];
        foreach ($this->payloads as $key => $payload) {
            $data[$key] = $this->converter->fromPayload($payload, null);
        }

        return $data;
    }

    /**
     * @return Payload[]
     */
    public function toPayloadArray(): array
    {
        if ($this->payloads !== null) {
            return $this->payloads;
        }

        if ($this->values === []) {
            return [];
        }

        $this->converter === null and throw new \LogicException('DataConverter is not set');

        $data = [];
        foreach ($this->values as $key => $value) {
            $data[$key] = $this->converter->toPayload($value);
        }

        return $data;
    }

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->converter = $converter;
    }

    /**
     * @return EncodedCollection
     */
    public static function empty(): EncodedCollection
    {
        $ev = new self();
        $ev->values = [];

        return $ev;
    }

    /**
     * @param array $values
     * @param DataConverterInterface|null $dataConverter
     *
     * @return EncodedCollection
     */
    public static function fromValues(array $values, DataConverterInterface $dataConverter = null): EncodedCollection
    {
        $ev = new self();
        $ev->values = $values;
        $ev->converter = $dataConverter;

        return $ev;
    }

    /**
     * @param iterable<array-key, Payload> $payloads
     * @param DataConverterInterface $dataConverter
     *
     * @return EncodedCollection
     */
    public static function fromPayloadCollection(
        iterable $payloads,
        DataConverterInterface $dataConverter,
    ): EncodedCollection {
        $ev = new self();
        $ev->payloads = \is_array($payloads) ? $payloads : \iterator_to_array($payloads);
        $ev->converter = $dataConverter;

        return $ev;
    }
}
