<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow\Attribute;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Common\IdReusePolicy;

/**
 * Whether the server allows reuse of a workflow ID.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class WorkflowIdReusePolicy
{
    public function __construct(
        public readonly IdReusePolicy $policy,
    ) {}
}
