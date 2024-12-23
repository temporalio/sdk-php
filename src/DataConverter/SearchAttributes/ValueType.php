<?php

declare(strict_types=1);

namespace Temporal\DataConverter\SearchAttributes;

enum ValueType: string
{
    case Bool = 'bool';
    case Float = 'float64';
    case Int = 'int';
    case Keyword = 'keyword';
    case KeywordList = 'keyword_list';
    case String = 'string';
    case Datetime = 'datetime';
}
