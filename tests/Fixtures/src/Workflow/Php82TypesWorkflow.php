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
use Temporal\Tests\Activity\Php82TypesActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class Php82TypesWorkflow
{
    #[WorkflowMethod(name: 'Php82TypesWorkflow')]
    public function handler(): iterable
    {
        $simple = Workflow::newActivityStub(
            Php82TypesActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2),
                ),
        );

        return [
            yield $simple->returnNull(null),
            yield $simple->returnTrue(true),
            yield $simple->returnFalse(false),
        ];
    }
}
