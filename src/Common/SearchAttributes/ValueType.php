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
}
