<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Carbon\CarbonInterval;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ParentWithChildAndTimerWorkflow
{
    #[WorkflowMethod(name: 'ParentWithChildAndTimerWorkflow')]
    public function handler(): string
    {
        $child = Workflow::executeChildWorkflow(
            'LongTimerWorkflow',
            [1800],
            ChildWorkflowOptions::new(),
        );

        Workflow::timer(CarbonInterval::minutes(30));

        return 'parent: ' . $child;
    }
}
