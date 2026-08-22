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
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class NestedActivityWorkflow
{
    #[WorkflowMethod(name: 'NestedActivityWorkflow')]
    public function handler(
        string $input,
    ) {
        // typed stub
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5)->withRetryOptions(
                RetryOptions::new()->withMaximumAttempts(1),
            ),
        );

        return [
            $this->doSomething($simple, $input),
            $this->doSomething($simple, 'another'),
        ];
    }

    /**
     * @param ActivityProxy<SimpleActivity> $simple
     */
    private function doSomething(ActivityProxy $simple, string $input): array
    {
        if ($input === 'error') {
            throw new \Exception('error from nested workflow action');
        }

        if ($input === 'failure') {
            $simple->fail();
            throw new \Exception('Unreachable statement');
        }

        $result = [];
        $result[] = $simple->echo($input);
        $result[] = $simple->echo($input);

        return $result;
    }
}
