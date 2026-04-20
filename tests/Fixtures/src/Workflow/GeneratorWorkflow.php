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
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class GeneratorWorkflow
{
    #[WorkflowMethod(name: 'GeneratorWorkflow')]
    public function handler(
        string $input
    ) {
        // typed stub
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5)->withRetryOptions(
                RetryOptions::new()->withMaximumAttempts(1)
            )
        );

        return [
            yield $this->doSomething($simple, $input),
            yield $this->doSomething($simple, 'another')
        ];
    }

    /**
     * @param ActivityProxy|SimpleActivity $simple
     */
    private function doSomething(ActivityProxy $simple, string $input): \Generator
    {
        $input === 'error' and throw new \Exception('error from generator');

        if ($input === 'failure') {
            yield $simple->fail();
            throw new \Exception('Unreachable statement');
        }

        $result = [];
        $result[] = yield $simple->echo($input);
        $result[] = yield $simple->echo($input);

        return $result;
    }
}
