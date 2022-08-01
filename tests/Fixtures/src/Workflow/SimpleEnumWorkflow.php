<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Unit\DTO\Enum\SimpleEnum;

#[Workflow\WorkflowInterface]
class SimpleEnumWorkflow
{
    #[WorkflowMethod(name: 'SimpleEnumWorkflow')]
    public function handler(SimpleEnum $enum): iterable
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2)
                )
        );

        return yield $simple->simpleEnum($enum);
    }
}
