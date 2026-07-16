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
class RepeatedActivityWorkflow
{
    #[WorkflowMethod(name: 'RepeatedActivityWorkflow')]
    public function handler(): iterable
    {
        $activity = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5),
        );

        $result = [];
        $result[] = yield $activity->echo('x');
        $result[] = yield $activity->echo('x');

        return $result;
    }
}
