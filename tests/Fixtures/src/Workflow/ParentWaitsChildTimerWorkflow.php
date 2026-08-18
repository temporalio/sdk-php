<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class ParentWaitsChildTimerWorkflow
{
    #[WorkflowMethod(name: 'ParentWaitsChildTimerWorkflow')]
    public function handler(int $seconds): iterable
    {
        return yield Workflow::executeChildWorkflow(
            'LongTimerWorkflow',
            [$seconds],
            ChildWorkflowOptions::new()->withTaskQueue('default'),
            'string',
        );
    }
}
