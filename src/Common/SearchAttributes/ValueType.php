<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes;

/**
 * @internal
 */
enum ValueType: string
{
    case Bool = 'bool';
    case Float = 'float64';
    case Int = 'int64';
    case Keyword = 'keyword';
    case KeywordList = 'keyword_list';
    case Text = 'string';
    case Datetime = 'datetime';

    /**
     * Parse a type string leniently, accepting the canonical form (e.g. "keyword"),
     * PascalCase form (e.g. "Keyword"), and SCREAMING_SNAKE_CASE proto enum form
     * (e.g. "INDEXED_VALUE_TYPE_KEYWORD").
     */
    public static function fromMetadata(string $type): ?self
    {
        // Try canonical form first (e.g. "keyword", "int64")
        $result = self::tryFrom($type);
        if ($result !== null) {
            return $result;
        }

        // PascalCase and SCREAMING_SNAKE_CASE forms
        return match ($type) {
            'Bool', 'INDEXED_VALUE_TYPE_BOOL' => self::Bool,
            'Double', 'INDEXED_VALUE_TYPE_DOUBLE' => self::Float,
            'Int', 'INDEXED_VALUE_TYPE_INT' => self::Int,
            'Keyword', 'INDEXED_VALUE_TYPE_KEYWORD' => self::Keyword,
            'KeywordList', 'INDEXED_VALUE_TYPE_KEYWORD_LIST' => self::KeywordList,
            'Text', 'INDEXED_VALUE_TYPE_TEXT' => self::Text,
            'Datetime', 'INDEXED_VALUE_TYPE_DATETIME' => self::Datetime,
            default => null,
        };
    }
}
