<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\Interceptor;

use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class HeadersWorkflow
{
    #[WorkflowMethod(name: 'InterceptorHeaderWorkflow')]
    public function handler(): iterable {
        // Run activity
        $activityResult = yield Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2),
                ),
        )->header();

        return [
            \iterator_to_array(Workflow::getCurrentContext()->getHeader()),
            $activityResult,
        ];
    }
}
