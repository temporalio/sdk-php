<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

/**
 * Used to categorize application failures, for example, to distinguish benign errors from others.
 *
 * @see \Temporal\Api\Enums\V1\ApplicationErrorCategory
 */
enum ApplicationErrorCategory: int
{
    case Unspecified = 0;

    /**
     * Expected application error with little/no severity.
     */
    case Benign = 1;
}
