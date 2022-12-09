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
use JetBrains\PhpStorm\Pure;
use Temporal\Api\Common\V1\Payload;
use Traversable;

/**
 * Collection of {@see Payload} instances.
 *
 * @template TKey of array-key
 * @template TValue of string
 *
 * @psalm-type TPayloadsCollection = Traversable&ArrayAccess&Countable
 */
abstract class EncodedPayloads
{
    /**
     * @var TPayloadsCollection|null
     */
    protected ?Traversable $payloads = null;

    /**
     * @var array<TKey, TValue>|null
     */
    protected ?array $values = null;

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
     * Can not be constructed directly.
     */
    protected function __construct()
    {
    }

    /**
     * @return int<0, max>
     */
    #[Pure]
    public function count(): int
    {
        return match (true) {
            $this->values !== null => \count($this->values),
            $this->payloads !== null => \count($this->payloads),
            default => 0,
        };
    }

    #[Pure]
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Returns collection of {@see Payloads}.
     *
     * @return array<TKey, Payload>
     */
    #[Pure]
    protected function toProtoCollection(): array
    {
        $data = [];

        if ($this->payloads !== null) {
            foreach ($this->payloads as $key => $payload) {
                $data[$key] = $payload;
            }
            return $data;
        }

        foreach ($this->values as $key => $value) {
            $data[$key] = $this->valueToPayload($value);
        }

        return $data;
    }

    #[Pure]
    protected function valueToPayload(mixed $value): Payload
    {
        return new Payload(['data' => $value]);
    }
}
