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
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class TimerWayWorkflow
{
    #[WorkflowMethod(name: 'TimerWayWorkflow')]
    public function handler(): iterable
    {
        $timerResolved = false;

        $timer = Workflow::timer(20)
            ->then(function () use (&$timerResolved) {
                $timerResolved = true;
            });

        yield Workflow::await($timer, fn() => true);

        return $timerResolved;
    }
}
