<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Ramsey\Uuid\Uuid;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class NonDetermenisticWorkflow
{
    #[WorkflowMethod(name: 'NonDetermenisticWorkflow')]
    public function handler(): iterable {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2)
                )
        );
        yield $simple->echo(\microtime());
        yield $simple->echo(\microtime());

        return yield $simple->echo((string)Uuid::uuid7());
    }
}
