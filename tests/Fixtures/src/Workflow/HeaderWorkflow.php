<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Generator;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

/**
 * @return Generator<mixed, mixed, mixed, array{
 *     array<array-key, string>,
 *     array<array-key, string>,
 *     array<array-key, string>
 * }>
 */
#[Workflow\WorkflowInterface]
class HeaderWorkflow
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
    #[WorkflowMethod(name: 'HeaderWorkflow')]
    public function handler(
        \stdClass|array|bool $subWorkflowHeader = false,
        \stdClass|array|null $activityHeader = null,
    ): iterable {
        // Run child workflow
        if ($subWorkflowHeader !== false) {
            // Child workflow header
            $header = match (true) {
                $subWorkflowHeader === true => null,
                \is_array($subWorkflowHeader) => $subWorkflowHeader,
                // Merge stdClass values with parent workflow header
                \is_object($subWorkflowHeader) => \array_merge(
                    \iterator_to_array(Workflow::getHeader()->getIterator()),
                    (array) $subWorkflowHeader,
                ),
            };
            // Run
            $subWorkflowResult = yield Workflow::newChildWorkflowStub(
                HeaderWorkflow::class,
                header: $header,
            )->handler();
        } else {
            $subWorkflowResult = [];
        }

        // Run activity
        $activityHeader = \is_object($activityHeader)
            ? \array_merge(
                \iterator_to_array(Workflow::getHeader()->getIterator()),
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
            \iterator_to_array(Workflow::getHeader()),
            $activityResult,
            $subWorkflowResult[0] ?? [],
        ];
    }
}
