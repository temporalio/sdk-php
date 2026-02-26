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
use Temporal\Common\RetryOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class ComplexExceptionalWorkflow
{
    #[WorkflowMethod(name: 'ComplexExceptionalWorkflow')]
    public function handler()
    {
        $child = Workflow::newChildWorkflowStub(
            ExceptionalActivityWorkflow::class,
            ChildWorkflowOptions::new()->withRetryOptions(
                (new RetryOptions())->withMaximumAttempts(1)
            )
        );

        return yield $child->handler();
    }
}
