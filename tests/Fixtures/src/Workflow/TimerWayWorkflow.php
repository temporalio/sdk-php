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

#[Workflow\WorkflowInterface]
class TimerWayWorkflow
{
    #[WorkflowMethod(name: 'TimerWayWorkflow')]
    public function handler(): bool
    {
        $timerResolved = false;

        $timer = Workflow::async(
            static function () use (&$timerResolved): void {
                Workflow::timer(20);
                $timerResolved = true;
            },
        );

        Workflow::await($timer, static fn() => true);

        return $timerResolved;
    }
}
