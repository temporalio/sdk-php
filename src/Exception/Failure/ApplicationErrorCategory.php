<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Temporal\Api\Enums\V1\ApplicationErrorCategory as ProtoCategory;

/**
 * Used to categorize application failures, for example, to distinguish benign errors from others.
 *
 * @see \Temporal\Api\Enums\V1\ApplicationErrorCategory
 */
enum ApplicationErrorCategory: int
{
    case Unspecified = ProtoCategory::APPLICATION_ERROR_CATEGORY_UNSPECIFIED;

    /**
     * Expected application error with little/no severity.
     */
    case Benign = ProtoCategory::APPLICATION_ERROR_CATEGORY_BENIGN;
}
