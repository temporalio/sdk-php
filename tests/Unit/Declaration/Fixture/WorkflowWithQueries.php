<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixture;

use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** @WorkflowInterface */
#[WorkflowInterface]
class WorkflowWithQueries
{
    /** @WorkflowMethod */
    #[WorkflowMethod]
    public function handler(): void
    {
    }

    /** @QueryMethod */
    #[QueryMethod]
    public function a(): void
    {
    }

    /** @QueryMethod */
    #[QueryMethod]
    public function b(): void
    {
    }
}
