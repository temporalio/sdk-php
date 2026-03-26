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
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class SideEffectWorkflow
{
    #[WorkflowMethod(name: 'SideEffectWorkflow')]
    public function handler(string $input): iterable
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5),
        );

        $result = yield Workflow::sideEffect(
            static function () use ($input): string {
                return $input . '-42';
            },
        );

        return yield $simple->lower($result);
    }
}
