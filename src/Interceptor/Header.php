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
use Temporal\DataConverter\EncodedPayloads;
use Traversable;

/**
 * @psalm-import-type TKey from HeaderInterface
 * @psalm-import-type TValue from HeaderInterface
 * @psalm-type TPayloadsCollection = Traversable<TKey, Payload>&ArrayAccess&Countable
 *
 * @extends EncodedPayloads<TKey, TValue>
 *
 * @psalm-immutable
 */
final class Header extends EncodedPayloads implements HeaderInterface
{
    /**
     * @param array<TKey, scalar|\Stringable> $values
     *
     * @return static
     */
    public static function fromValues(array $values): static
    {
        $ev = new static();
        $ev->values = [];

        foreach ($values as $key => $value) {
            $ev->values[$key] = (string) $value;
        }

        return $ev;
    }

    /**
     * @param ArrayAccess $payloads
     *
     * @return static
     */
    public static function fromPayloadCollection(Traversable $payloads): static
    {
        $ev = new static();
        $ev->payloads = $payloads;

        return $ev;
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        if ($this->values !== null) {
            yield from $this->values;
        } else {
            foreach ($this->payloads as $key => $payload) {
                yield $key => $payload->getData();
            }
        }
    }

    public function getValue(int|string $index): ?string
    {
        return match (true) {
            $this->values !== null => $this->values[$index] ?? null,
            $this->payloads !== null => $this->payloads->offsetExists($index)
                ? $this->payloads->offsetGet($index)->getData()
                : null,
            default => null,
        };
    }

    public function withValue(int|string $key, string $value): self
    {
        $clone = clone $this;

        if ($this->values !== null) {
            $clone->values[$key] = $value;
            return $clone;
        }

        $clone->payloads = clone $this->payloads;
        $clone->payloads->offsetSet($key, new Payload(['data' => $value]));

        return $clone;
    }

    public function toHeader(): ProtoHeader
    {
        return new ProtoHeader(['fields' => $this->toProtoCollection()]);
    }
}
