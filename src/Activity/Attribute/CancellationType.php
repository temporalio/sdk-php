<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity\Attribute;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Activity\ActivityCancellationType;

/**
 * Whether to wait for canceled activity to be completed
 * (activity can be failed, completed, cancel accepted).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class CancellationType
{
    public function __construct(
        public readonly ActivityCancellationType $type,
    ) {}
}
