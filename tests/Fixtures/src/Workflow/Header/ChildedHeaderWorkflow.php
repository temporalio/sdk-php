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
use Temporal\Tests\Interceptor\HeaderChanger;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
final class ChildedHeaderWorkflow
{
    use HandleTrait;

    public const WORKFLOW_NAME = 'Header.ChildedHeaderWorkflow';

    /**
     * @param array|null $currentHeader Header that will be set by {@see HeaderChanger} interceptor on execute
     *        - null: don't change Header
     *        - array: will be passed into the current workflow as is without merging with inbound Header
     * @param array|bool $subWorkflowHeader Header for child workflow:
     *        - false: don't run child workflow
     *        - true: run child workflow without passing header set
     *        - array: will be passed into child workflow as is without merging with parent header
     * @param array|null $activityHeader {@see HandleTrait::runActivity()}
     *
     * @return Generator<mixed, mixed, mixed, array{array, array, array}> Returns array of headers:
     *         - [0] - header from parent workflow
     *         - [1] - header from activity
     *         - [2] - header from child workflow
     */
    #[WorkflowMethod(name: self::WORKFLOW_NAME)]
    public function handler(
        array|null $currentHeader = [],
        array|bool $subWorkflowHeader = false,
        array|null $activityHeader = null,
    ): iterable {
        // Run child workflow
        if ($subWorkflowHeader !== false) {
            $subWorkflowResult = yield Workflow::newChildWorkflowStub(self::class)
                ->handler($subWorkflowHeader === true ? null : $subWorkflowHeader, false, $activityHeader);
        } else {
            $subWorkflowResult = [];
        }

        yield from $generator = $this->runActivity($activityHeader);
        return [...$generator->getReturn(), $subWorkflowResult[0] ?? []];
    }
}
