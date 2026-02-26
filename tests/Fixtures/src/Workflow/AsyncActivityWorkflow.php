<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityCancellationType;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class AsyncActivityWorkflow
{
    #[WorkflowMethod(name: 'AsyncActivityWorkflow')]
    public function handler()
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(20)
                ->withCancellationType(ActivityCancellationType::WAIT_CANCELLATION_COMPLETED)
                ->withRetryOptions(RetryOptions::new()
                    ->withMaximumAttempts(1)
                    ->withInitialInterval(1)
                    ->withMaximumInterval(2)
                )
        );

        return yield $simple->external();
    }
}
