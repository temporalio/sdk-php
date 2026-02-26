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
use Temporal\Common\WorkflowIdConflictPolicy as ConflictPolicy;

/**
 * Defines the action to take if there is already a running workflow with the
 * same workflow ID.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class WorkflowIdConflictPolicy
{
    public function __construct(
        public readonly ConflictPolicy $policy,
    ) {}
}
