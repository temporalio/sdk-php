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
use React\Promise\PromiseInterface;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Workflow\ReturnType;
use Traversable;

/**
 * List of typed values.
 *
 * @psalm-type TPayloadsCollection = Traversable&ArrayAccess&Countable
 * @psalm-type TKey = int
 * @psalm-type TValue = string
 */
class EncodedValues implements ValuesInterface
{
    /**
     * @var TPayloadsCollection|null
     */
    protected ?\Traversable $payloads = null;

    /**
     * @var array<TKey, TValue>|null
     */
    protected ?array $values = null;

    /**
     * @var DataConverterInterface|null
     */
    private ?DataConverterInterface $converter = null;

    /**
     * Can not be constructed directly.
     */
    private function __construct() {}

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
     * @param Payloads $payloads
     * @param DataConverterInterface $dataConverter
     *
     * @return EncodedValues
     */
    public static function fromPayloads(Payloads $payloads, DataConverterInterface $dataConverter): EncodedValues
    {
        return static::fromPayloadCollection($payloads->getPayloads(), $dataConverter);
    }

    /**
     * @param DataConverterInterface $converter
     * @param ValuesInterface $values
     * @param int $offset
     * @param int|null $length
     *
     * @return ValuesInterface
     */
    public static function sliceValues(
        DataConverterInterface $converter,
        ValuesInterface $values,
        int $offset,
        int $length = null,
    ): ValuesInterface {
        $payloads = $values->toPayloads();
        $newPayloads = new Payloads();
        $newPayloads->setPayloads(\array_slice(\iterator_to_array($payloads->getPayloads()), $offset, $length));

        return self::fromPayloads($newPayloads, $converter);
    }

    /**
     * Decode promise response upon returning it to the domain layer.
     *
     * @param PromiseInterface $promise
     * @param string|\ReflectionClass|\ReflectionType|Type|null $type
     *
     * @return PromiseInterface
     */
    public static function decodePromise(PromiseInterface $promise, $type = null): PromiseInterface
    {
        return $promise->then(
            static function (mixed $value) use ($type) {
                if (!$value instanceof ValuesInterface || $value instanceof \Throwable) {
                    return $value;
                }

                return $value->getValue(0, $type);
            },
        );
    }

    /**
     * @param array $values
     * @param DataConverterInterface|null $dataConverter
     *
     * @return static
     */
    public static function fromValues(array $values, DataConverterInterface $dataConverter = null): static
    {
        $ev = new static();
        $ev->values = \array_values($values);
        $ev->converter = $dataConverter;

        return $ev;
    }

    /**
     * @param TPayloadsCollection $payloads
     * @param ?DataConverterInterface $dataConverter
     *
     * @return static
     */
    public static function fromPayloadCollection(
        \Traversable $payloads,
        ?DataConverterInterface $dataConverter = null,
    ): static {
        $ev = new static();
        $ev->payloads = $payloads;
        $ev->converter = $dataConverter;

        return $ev;
    }

    public function toPayloads(): Payloads
    {
        return new Payloads(['payloads' => $this->toProtoCollection()]);
    }

    public function getValue(int|string $index, $type = null): mixed
    {
        if (\is_array($this->values) && \array_key_exists($index, $this->values)) {
            return $this->values[$index];
        }

        $count = $this->count();
        // External SDKs might return an empty array with metadata, alias to null
        // Most likely this is a void type
        if ($index === 0 && $count === 0 && $this->isVoidType($type)) {
            return null;
        }

        $count > $index or throw new \OutOfBoundsException("Index {$index} is out of bounds.");
        $this->converter === null and throw new \LogicException('DataConverter is not set.');

        \assert($this->payloads !== null);
        return $this->converter->fromPayload(
            $this->payloads[$index],
            $type,
        );
    }

    public function getValues(): array
    {
        $result = (array) $this->values;

        if (empty($this->payloads)) {
            return $result;
        }

        $this->converter === null and throw new \LogicException('DataConverter is not set.');

        foreach ($this->payloads as $key => $payload) {
            $result[$key] = $this->converter->fromPayload($payload, null);
        }

        return $result;
    }

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->converter = $converter;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return match (true) {
            $this->values !== null => \count($this->values),
            $this->payloads !== null => \count($this->payloads),
            default => 0,
        };
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    private function isVoidType(mixed $type = null): bool
    {
        return match (true) {
            $type === null => true,
            \is_string($type) =>  \in_array($type, [Type::TYPE_VOID, Type::TYPE_NULL, Type::TYPE_ANY], true),
            $type instanceof Type => $type->allowsNull(),
            $type instanceof ReturnType => $type->nullable,
            $type instanceof \ReflectionNamedType => $type->getName() === Type::TYPE_VOID || $type->allowsNull(),
            $type instanceof \ReflectionUnionType => $type->allowsNull(),
            default => false,
        };
    }

    /**
     * Returns collection of {@see Payloads}.
     *
     * @return array<TKey, Payload>
     */
    private function toProtoCollection(): array
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

    private function valueToPayload(mixed $value): Payload
    {
        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set');
        }
        return $this->converter->toPayload($value);
    }
}
