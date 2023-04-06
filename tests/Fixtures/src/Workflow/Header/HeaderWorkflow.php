<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\Header;

use Generator;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;

#[Workflow\WorkflowInterface]
abstract class HeaderWorkflow
{
    /**
     * @param array|bool $subWorkflowHeader Header for child workflow:
     *        - false: don't run child workflow
     *        - true: run child workflow without passing header set
     *        - array: will be passed into child workflow as is without merging with parent header
     *        - stdClass: will be converted to array and merged with parent workflow header
     * @param array|bool $activityHeader Header for activity:
     *        - null: run activity with {@see null} header value
     *        - array: will be passed into activity as is without merging with workflow header
     *        - stdClass: will be converted to array and merged with workflow header
     *
     * @return Generator<mixed, mixed, mixed, array{array, array, array}> Returns array of headers:
     *         - [0] - header from parent workflow
     *         - [1] - header from activity
     *         - [2] - header from child workflow
     */
    public function handler(
        \stdClass|array|bool $subWorkflowHeader = false,
        \stdClass|array|null $activityHeader = null,
    ): iterable {
        // Run child workflow
        if ($subWorkflowHeader !== false) {
            $subWorkflowResult = yield Workflow::newChildWorkflowStub(self::class)->handler();
        } else {
            $subWorkflowResult = [];
        }

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
            $subWorkflowResult[0] ?? [],
        ];
    }
}
