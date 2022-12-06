<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use Countable;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;

/**
 * Collection of {@see Payload} instances.
 */
class EncodedPayloads
{
    /**
     * @var DataConverterInterface|null
     */
    protected ?DataConverterInterface $converter = null;

    /**
     * @var iterable<array-key, Payloads>|null
     */
    protected ?iterable $payloads = null;

    /**
     * @var array|null
     */
    protected ?array $values = null;

    /**
     * Can not be constructed directly.
     */
    protected function __construct()
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
            \assert($this->payloads instanceof Countable);
            return $this->payloads->count();
        }

        return 0;
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @param Type|string|null $type
     * @return mixed
     */
    public function getValue(int|string $index, $type = null): mixed
    {
        if (\is_array($this->values) && \array_key_exists($index, $this->values)) {
            return $this->values[$index];
        }

        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set');
        }

        return $this->converter->fromPayload($this->payloads[$index], $type);
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
    public static function empty(): static
    {
        $ev = new static();
        $ev->values = [];

        return $ev;
    }

    /**
     * @param array $values
     * @param DataConverterInterface|null $dataConverter
     * @return EncodedValues
     */
    public static function fromValues(array $values, DataConverterInterface $dataConverter = null): static
    {
        $ev = new static();
        $ev->values = \array_values($values);
        $ev->converter = $dataConverter;

        return $ev;
    }

    /**
     * @param iterable<array-key, Payload> $payloads
     * @param DataConverterInterface $dataConverter
     * @return EncodedValues
     */
    public static function fromPayloadCollection(
        iterable $payloads,
        DataConverterInterface $dataConverter,
    ): static {
        $ev = new static();
        $ev->payloads = $payloads;
        $ev->converter = $dataConverter;

        return $ev;
    }


    /**
     * @return array<array-key, Payload>
     */
    public function toProtoCollection(): array
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

        return $data;
    }
}
