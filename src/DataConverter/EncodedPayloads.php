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
use Temporal\Api\Common\V1\Payload;
use Traversable;

/**
 * Collection of {@see Payload} instances.
 *
 * @template TKey of array-key
 * @template TValue of string
 *
 * @psalm-type TPayloadsCollection = Traversable<TKey, Payload>&ArrayAccess&Countable
 */
abstract class EncodedPayloads
{
    /**
     * @var TPayloadsCollection
     */
    protected ?Traversable $payloads = null;

    /**
     * @var array<TKey, TValue>|null
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
     * @param TKey $index
     *
     * @return TValue
     */
    public function getValue(int|string $index): mixed
    {
        if (\is_array($this->values) && \array_key_exists($index, $this->values)) {
            return $this->values[$index];
        }

        return $this->payloads[$index]->getData();
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
     * @param array<TKey, TValue> $values
     *
     * @return static
     */
    public static function fromValues(array $values): static
    {
        $ev = new static();
        $ev->values = \array_values($values);

        return $ev;
    }

    /**
     * @param TPayloadsCollection $payloads
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
     * @return array<array-key, Payload>
     */
    public function toProtoCollection(): array
    {
        if ($this->payloads !== null) {
            return $this->payloads;
        }

        $data = [];
        foreach ($this->values as $value) {
            $data[] = $this->valueToPayload($value);
        }

        return $data;
    }

    protected function valueToPayload(string $value): Payload
    {
        return new Payload(['data' => $value]);
    }
}
