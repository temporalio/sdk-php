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
class LocalActivityReturningWorkflow
{
    #[WorkflowMethod(name: 'LocalActivityReturningWorkflow')]
    public function handler(string $input): iterable
    {
        return yield Workflow::newActivityStub(
            JustLocalActivity::class,
            LocalActivityOptions::new()->withStartToCloseTimeout('10 seconds'),
        )->echo($input);
    }
}
