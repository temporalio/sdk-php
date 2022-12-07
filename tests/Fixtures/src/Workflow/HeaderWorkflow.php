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
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

/**
 * @return Generator<mixed, mixed, mixed, array{array<array-key, string>, array<array-key, string>}>
 */
#[Workflow\WorkflowInterface]
class HeaderWorkflow
{
    #[WorkflowMethod(name: 'HeaderWorkflow')]
    // #[Workflow\ReturnType(\Temporal\DataConverter\Type::TYPE_ARRAY)]
    public function handler(): iterable
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2),
                ),
        );

        yield $simple->echo('foo');
        $activityHeader = [];

        return [
            \iterator_to_array(Workflow::getHeader()),
            $activityHeader,
        ];
    }
}
