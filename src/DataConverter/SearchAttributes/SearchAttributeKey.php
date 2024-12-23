<?php

declare(strict_types=1);

namespace Temporal\DataConverter\SearchAttributes;

/**
 * @template-covariant TValue
 * @psalm-immutable
 */
abstract class SearchAttributeKey implements \JsonSerializable
{
    /**
     * @param non-empty-string $key
     * @param TValue $value
     */
    final protected function __construct(
        public readonly string $key,
        public readonly mixed $value,
    ) {}

    /**
     * @param non-empty-string $key
     */
    public static function bool(string $key, bool $value): BoolValue
    {
        return new BoolValue($key, $value);
    }

    /**
     * @param non-empty-string $key
     */
    public static function integer(string $key, int $value): IntValue
    {
        return new IntValue($key, $value);
    }

    /**
     * @param non-empty-string $key
     */
    public static function float(string $key, float $value): FloatValue
    {
        return new FloatValue($key, $value);
    }

    /**
     * @param non-empty-string $key
     */
    public static function keyword(string $key, string $value): KeywordValue
    {
        return new KeywordValue($key, $value);
    }

    /**
     * @param non-empty-string $key
     */
    public static function string(string $key, string $value): StringValue
    {
        return new StringValue($key, $value);
    }

    public static function datetime(string $key, \DateTimeInterface|string $value): DatetimeValue
    {
        return new DatetimeValue($key, match (true) {
            \is_string($value) => new \DateTimeImmutable($value),
            $value instanceof \DateTimeImmutable => $value,
            default => \DateTimeImmutable::createFromInterface($value),
        });
    }

    /**
     * @param non-empty-string $key
     * @param iterable<scalar> $value
     */
    public static function keywordList(string $key, iterable $value): KeywordListValue
    {
        /** @var list<string> $values */
        $values = [];
        foreach ($value as $item) {
            $values[] = (string) $item;
        }

        return new KeywordListValue($key, $values);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'value' => $this->getValue(),
        ];
    }

    abstract protected function getType(): string;

    protected function getValue(): mixed
    {
        return $this->value;
    }
}
