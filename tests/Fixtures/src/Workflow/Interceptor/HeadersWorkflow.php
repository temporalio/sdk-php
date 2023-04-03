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

#[Workflow\WorkflowInterface]
class HeadersWorkflow
{
    #[WorkflowMethod(name: 'InterceptorHeaderWorkflow')]
    public function handler(
        \stdClass|array|null $activityHeader = null,
    ): iterable {
        // Run activity
        $activityHeader = \is_object($activityHeader)
            ? \array_merge(
                \iterator_to_array(Workflow::getCurrentContext()->getHeader()->getIterator()),
                (array) $activityHeader,
            )
            : $activityHeader;
        $activityResult = yield Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2),
                ),
            $activityHeader,
        )->header();

        return [
            \iterator_to_array(Workflow::getCurrentContext()->getHeader()),
            $activityResult,
        ];
    }
}
