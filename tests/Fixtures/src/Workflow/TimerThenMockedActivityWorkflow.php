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
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class TimerThenMockedActivityWorkflow
{
    #[WorkflowMethod(name: 'TimerThenMockedActivityWorkflow')]
    public function handler(int $seconds): iterable
    {
        yield Workflow::timer($seconds);

        $activity = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );

        return yield $activity->echo('ping');
    }
}
