<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class DirectStepsWorkflow
{
    #[WorkflowMethod(name: 'DirectStepsWorkflow')]
    public function handler(): string
    {
        return $this->runSteps();
    }

    private function runSteps(): string
    {
        Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout(5),
        )->empty();
        Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout(5),
        )->lower('Hello World!');

        return 'bar';
    }
}
