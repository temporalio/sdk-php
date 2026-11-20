<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Support;

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;

trait EncodesValues
{
    protected static function dataConverter(): DataConverterInterface
    {
        return DataConverter::createDefault();
    }

    protected static function encode(mixed $value): EncodedValues
    {
        return EncodedValues::fromValues([$value], self::dataConverter());
    }
}
