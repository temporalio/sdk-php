<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payloads;

final class PayloadComparator
{
    public static function equals(?Payloads $left, ?Payloads $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left->serializeToString() === $right->serializeToString();
    }
}
