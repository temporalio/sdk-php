<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor;

use ArrayAccess;
use Countable;
use Temporal\Api\Common\V1\Header as ProtoHeader;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverterInterface;
use Traversable;

/**
 * @psalm-type TPayloadsCollection = Traversable&ArrayAccess&Countable
 * @psalm-type TKey = array-key
 * @psalm-type TValue = mixed
 */
final class Header implements HeaderInterface
{
    /**
     * @var DataConverterInterface|null
     */
    private ?DataConverterInterface $converter = null;

    /**
     * @var TPayloadsCollection|null
     */
    private ?Traversable $payloads = null;

    /**
     * @var array<TKey, TValue>
     */
    private array $values = [];

    /**
     * Can not be constructed directly.
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

    /**
     * @param array<TKey, scalar|\Stringable> $values
     *
     * @return static
     */
    public static function fromValues(array $values): self
    {
        $ev = new self();
        foreach ($values as $key => $value) {
            $ev->values[$key] = (string) $value;
        }

        return $ev;
    }

    /**
     * @param ArrayAccess&Traversable $payloads
     *
     * @return static
     */
    public static function fromPayloadCollection(
        Traversable $payloads,
        ?DataConverterInterface $dataConverter = null,
    ): self {
        \assert($payloads instanceof ArrayAccess);

        $ev = new self();
        $ev->payloads = $payloads;
        $ev->converter = $dataConverter;

        return $ev;
    }

    /**
     * @return static
     */
    public static function empty(): self
    {
        return new self();
    }

    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->converter = $converter;
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        yield from $this->values;
        if ($this->payloads !== null) {
            \assert($this->converter !== null);

            foreach ($this->payloads as $key => $payload) {
                yield $key => $this->converter->fromPayload($payload, null);
            }
        }
    }

    public function getValue(int|string $index, mixed $type = null): mixed
    {
        if (\array_key_exists($index, $this->values)) {
            return $this->values[$index];
        }

        if ($this->payloads === null || !$this->payloads->offsetExists($index)) {
            return null;
        }

        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set.');
        }

        return $this->converter->fromPayload($this->payloads[$index], $type);
    }

    public function withValue(int|string $key, string $value): self
    {
        $clone = clone $this;
        $clone->values[$key] = $value;
        $clone->payloads?->offsetUnset($key);

        return $clone;
    }

    public function toHeader(): ProtoHeader
    {
        return new ProtoHeader(['fields' => $this->toProtoCollection()]);
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->values) + ($this->payloads !== null ? \count($this->payloads) : 0);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Returns collection of {@see Payloads}.
     *
     * @return array<TKey, Payload>
     */
    private function toProtoCollection(): array
    {
        $data = $this->payloads !== null ? \iterator_to_array($this->payloads) : [];

        if ($this->values !== []) {
            if ($this->converter === null) {
                throw new \LogicException('DataConverter is not set.');
            }

            foreach ($this->values as $key => $value) {
                $data[$key] = $this->converter->toPayload($value);
            }
        }

        return $data;
    }
}
