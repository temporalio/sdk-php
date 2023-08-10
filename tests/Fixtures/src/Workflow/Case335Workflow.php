<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class Case335Workflow
{
    private bool $exit = false;
    private bool $timerRun = false;

    #[SignalMethod('signal')]
    public function signal()
    {
        $this->exit = true;

        yield Workflow::timer(1);

        $this->timerRun = true;
    }

    #[WorkflowMethod('case335_workflow')]
    public function run()
    {
        yield Workflow::await(fn() => $this->exit);
        return $this->timerRun;
    }
}
