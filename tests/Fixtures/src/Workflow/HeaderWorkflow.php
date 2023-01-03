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
    private SimpleActivity|ActivityProxy $activity;

    public function __construct()
    {
        $this->activity = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2),
                ),
        );
    }

    /**
     * @param array|bool $subWorkflowHeader Header for child workflow:
     *  - false: don't run child workflow
     *  - true: run child workflow without passing header set
     *  - array: will be passed into child workflow as is without merging with parent header
     *  - stdClass: will be converted to array and merged with parent workflow header
     */
    #[WorkflowMethod(name: 'HeaderWorkflow')]
    public function handler(\stdClass|array|bool $subWorkflowHeader = false): iterable
    {
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

        yield $this->activity->echo('foo');
        $activityHeader = [];

        return [
            \iterator_to_array(Workflow::getHeader()),
            $activityHeader,
            $subWorkflowResult[0],
        ];
    }
}
