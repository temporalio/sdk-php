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
     *  - true: run child workflow without ChildWorkflowOptions
     *  - array: run child workflow with ChildWorkflowOptions. The array value will be passed into it the options
     *  - stdClass will be converted to array
     */
    #[WorkflowMethod(name: 'HeaderWorkflow')]
    public function handler(array|\stdClass|bool $subWorkflowHeader = false): iterable
    {
        // Run child workflow
        if ($subWorkflowHeader !== false) {
            // Child workflow header
            if ($subWorkflowHeader !== true) {
                $options = Workflow\ChildWorkflowOptions::new()
                    ->withHeader((array)$subWorkflowHeader);
            }
            // Run
            $subWorkflowResult = yield Workflow::newChildWorkflowStub(
                HeaderWorkflow::class,
                $options ?? null,
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
