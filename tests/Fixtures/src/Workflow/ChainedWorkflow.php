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
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ChainedWorkflow
{
    #[WorkflowMethod(name: 'ChainedWorkflow')]
    public function handler(string $input): string
    {
        $opts = ActivityOptions::new()->withStartToCloseTimeout(5);

        $result = Workflow::executeActivity(
            'SimpleActivity.echo',
            [$input],
            $opts,
        );

        return Workflow::executeActivity(
            'SimpleActivity.lower',
            ['Result:' . $result],
            $opts,
        );
    }
}
