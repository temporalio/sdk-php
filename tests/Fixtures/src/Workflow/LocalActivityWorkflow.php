<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\LocalActivityOptions;
use Temporal\Tests\Activity\JustLocalActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class LocalActivityWorkflow
{
    #[WorkflowMethod(name: 'LocalActivityWorkflow')]
    public function handler()
    {
        yield Workflow::newActivityStub(
            JustLocalActivity::class,
            LocalActivityOptions::new()->withStartToCloseTimeout('10 seconds'),
        )->echo('test');
    }
}
