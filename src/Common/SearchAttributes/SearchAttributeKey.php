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

/**
 * @psalm-immutable
 */
abstract class SearchAttributeKey
{
    /**
     * @param non-empty-string $key
     */
    final protected function __construct(
        private readonly string $key,
    ) {}

    /**
     * @param non-empty-string $key
     */
    public static function forBool(string $key): BoolValue
    {
        return new BoolValue($key);
    }

    /**
     * @param non-empty-string $key
     */
    public static function forInteger(string $key): IntValue
    {
        return new IntValue($key);
    }

    /**
     * @param non-empty-string $key
     */
    public static function forFloat(string $key): FloatValue
    {
        return new FloatValue($key);
    }

    /**
     * @param non-empty-string $key
     */
    public static function forKeyword(string $key): KeywordValue
    {
        return new KeywordValue($key);
    }

    /**
     * @param non-empty-string $key
     */
    public static function forString(string $key): StringValue
    {
        return new StringValue($key);
    }

    /**
     * @param non-empty-string $key
     */
    public static function forDatetime(string $key): DatetimeValue
    {
        return new DatetimeValue($key);
    }

    /**
     * @param non-empty-string $key
     */
    public static function forKeywordList(string $key): KeywordListValue
    {
        return new KeywordListValue($key);
    }

    public function valueUnset(): SearchAttributeUpdate
    {
        return SearchAttributeUpdate::valueUnset($this->key, $this->getType());
    }

    protected function prepareValueSet(mixed $value): SearchAttributeUpdate
    {
        return SearchAttributeUpdate::valueSet($this->key, $this->getType(), $value);
    }

    abstract protected function getType(): ValueType;
}
