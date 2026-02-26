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
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class ChainedWorkflow
{
    #[WorkflowMethod(name: 'ChainedWorkflow')]
    public function handler(string $input): iterable
    {
        $opts = ActivityOptions::new()->withStartToCloseTimeout(5);

        return yield Workflow::executeActivity(
            'SimpleActivity.echo',
            [$input],
            $opts
        )->then(function ($result) use ($opts) {
            return Workflow::executeActivity(
                'SimpleActivity.lower',
                ['Result:' . $result],
                $opts
            );
        });
    }
}
