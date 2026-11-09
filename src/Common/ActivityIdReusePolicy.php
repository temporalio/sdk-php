<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

/**
 * @see \Temporal\Api\Enums\V1\ActivityIdReusePolicy
 */
enum ActivityIdReusePolicy: int
{
    case Unspecified = 0;
    case AllowDuplicate = 1;
    case AllowDuplicateFailedOnly = 2;
    case RejectDuplicate = 3;
}
