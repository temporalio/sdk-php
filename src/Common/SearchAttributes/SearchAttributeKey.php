<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes;

use Temporal\Common\SearchAttributes\SearchAttributeKey\BoolValue;
use Temporal\Common\SearchAttributes\SearchAttributeKey\DatetimeValue;
use Temporal\Common\SearchAttributes\SearchAttributeKey\FloatValue;
use Temporal\Common\SearchAttributes\SearchAttributeKey\IntValue;
use Temporal\Common\SearchAttributes\SearchAttributeKey\KeywordListValue;
use Temporal\Common\SearchAttributes\SearchAttributeKey\KeywordValue;
use Temporal\Common\SearchAttributes\SearchAttributeKey\StringValue;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate\ValueSet;

/**
 * @psalm-immutable
 * @method ValueSet valueSet(mixed $value)
 */
abstract class SearchAttributeKey
{
    /**
     * @param non-empty-string $name
     */
    final protected function __construct(
        private readonly string $name,
    ) {}

    /**
     * @param non-empty-string $name
     */
    public static function forBool(string $name): BoolValue
    {
        return new BoolValue($name);
    }

    /**
     * @param non-empty-string $name
     */
    public static function forInteger(string $name): IntValue
    {
        return new IntValue($name);
    }

    /**
     * @param non-empty-string $name
     */
    public static function forFloat(string $name): FloatValue
    {
        return new FloatValue($name);
    }

    /**
     * @param non-empty-string $name
     */
    public static function forKeyword(string $name): KeywordValue
    {
        return new KeywordValue($name);
    }

    /**
     * @param non-empty-string $name
     */
    public static function forString(string $name): StringValue
    {
        return new StringValue($name);
    }

    /**
     * @param non-empty-string $name
     */
    public static function forDatetime(string $name): DatetimeValue
    {
        return new DatetimeValue($name);
    }

    /**
     * @param non-empty-string $name
     */
    public static function forKeywordList(string $name): KeywordListValue
    {
        return new KeywordListValue($name);
    }

    /**
     * @param non-empty-string $name
     */
    public static function for(ValueType $tryFrom, string $name): self
    {
        return match ($tryFrom) {
            ValueType::Bool => self::forBool($name),
            ValueType::Int => self::forInteger($name),
            ValueType::Float => self::forFloat($name),
            ValueType::Keyword => self::forKeyword($name),
            ValueType::String => self::forString($name),
            ValueType::Datetime => self::forDatetime($name),
            ValueType::KeywordList => self::forKeywordList($name),
        };
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function valueUnset(): SearchAttributeUpdate
    {
        return SearchAttributeUpdate::valueUnset($this->name, $this->getType());
    }

    abstract public function getType(): ValueType;

    protected function prepareValueSet(mixed $value): SearchAttributeUpdate
    {
        return SearchAttributeUpdate::valueSet($this->name, $this->getType(), $value);
    }
}
