<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Temporal\Api\Common\V1\Payload;
use Traversable;

/**
 * @psalm-type TPayloadsCollection = Traversable&ArrayAccess&Countable
 * @psalm-type TKey = array-key
 * @psalm-type TValue = mixed
 *
 * @implements IteratorAggregate<TKey, TValue>
 */
class EncodedCollection implements IteratorAggregate, Countable
{
    /**
     * @var DataConverterInterface|null
     */
    private ?DataConverterInterface $converter = null;

    /**
     * @var TPayloadsCollection|null
     */
    private ?ArrayAccess $payloads = null;

    private array $values = [];

    /**
     * Cannot be constructed directly.
     */
    private function __construct()
    {
    }

    public function __clone()
    {
        if ($this->payloads !== null) {
            $this->payloads = clone $this->payloads;
        }
    }

    public function count(): int
    {
        return \count($this->values) + ($this->payloads?->count() ?? 0);
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
    public function getValue(int|string $name, mixed $type = null): mixed
    {
        if (\array_key_exists($name, $this->values)) {
            return $this->values[$name];
        }

        if ($this->payloads === null || !$this->payloads->offsetExists($name)) {
            return null;
        }

        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set.');
        }

        return $this->converter->fromPayload($this->payloads[$name], $type);
    }

    public function getValues(): array
    {
        $result = $this->values;

        if (empty($this->payloads)) {
            return $result;
        }

        $this->converter === null and throw new \LogicException('DataConverter is not set.');

        foreach ($this->payloads as $key => $payload) {
            $result[$key] = $this->converter->fromPayload($payload, null);
        }

        return $result;
    }

    public function getIterator(): Traversable
    {
        yield from $this->values;
        if ($this->payloads !== null) {
            $this->converter !== null or $this->payloads->count() === 0
            or throw new \LogicException('DataConverter is not set.');

            foreach ($this->payloads as $key => $payload) {
                yield $key => $this->converter->fromPayload($payload, null);
            }
        }
    }

    /**
     * @return Payload[]
     */
    public function toPayloadArray(): array
    {
        $data = $this->payloads !== null
            ? \iterator_to_array($this->payloads)
            : [];

        if (empty($this->values)) {
            return $data;
        }

        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set.');
        }

        foreach ($this->values as $key => $value) {
            $data[$key] = $this->converter->toPayload($value);
        }

        return $data;
    }

    public function withValue(int|string $name, mixed $value): static
    {
        $clone = clone $this;
        if ($value instanceof Payload) {
            $clone->payloads ??= new \ArrayIterator();
            $clone->payloads->offsetSet($name, $value);
            unset($clone->values[$name]);
            return $clone;
        }

        // The value is not a Payload
        $clone->values[$name] = $value;
        $clone->payloads?->offsetUnset($name);
        return $clone;
    }

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->converter = $converter;
    }

    /**
     * @return static
     */
    public static function empty(): static
    {
        $ev = new static();
        $ev->values = [];

        return $ev;
    }

    /**
     * @param iterable $values
     * @param DataConverterInterface|null $dataConverter
     *
     * @return static
     */
    public static function fromValues(iterable $values, ?DataConverterInterface $dataConverter = null): static
    {
        $ev = new static();
        foreach ($values as $key => $value) {
            $ev->values[$key] = $value;
        }
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
        array|ArrayAccess $payloads,
        DataConverterInterface $dataConverter,
    ): static {
        $ev = new static();
        $ev->payloads = \is_array($payloads)
            ? new \ArrayIterator($payloads)
            : $payloads;
        $ev->converter = $dataConverter;

        return $ev;
    }
}
