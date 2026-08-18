<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ParentWithStubbableChildWorkflow
{
    #[WorkflowMethod(name: 'ParentWithStubbableChildWorkflow')]
    public function handler(string $childType, string $input): iterable
    {
        try {
            $result = yield Workflow::executeChildWorkflow(
                $childType,
                [$input],
                ChildWorkflowOptions::new()
                    ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
            );
        } catch (\Throwable $e) {
            $chain = ['error'];
            for ($current = $e; $current !== null; $current = $current->getPrevious()) {
                $chain[] = $current::class . ': ' . $current->getMessage();
            }

            return $chain;
        }

        return ['ok', $result];
    }
}
