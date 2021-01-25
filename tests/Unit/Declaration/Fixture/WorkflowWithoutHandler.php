<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixture;

use Temporal\Workflow\WorkflowInterface;

/** @WorkflowInterface */
#[WorkflowInterface]
class WorkflowWithoutHandler
{
    public function handler()
    {
        return 42;
    }
}
