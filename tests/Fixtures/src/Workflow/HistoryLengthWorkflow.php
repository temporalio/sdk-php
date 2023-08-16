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
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class HistoryLengthWorkflow
{
    #[WorkflowMethod(name: 'HistoryLengthWorkflow')]
    public function handler(string $input): iterable
    {
        $result = [Workflow::getInfo()->historyLength];
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5),
        );

        $str = yield Workflow::sideEffect(
            function () use ($input) {
                return $input . '-42';
            },
        );
        $result[] = Workflow::getInfo()->historyLength;

        yield $simple->lower($str);
        $result[] = Workflow::getInfo()->historyLength;

        yield $simple->lower($str);
        $result[] = Workflow::getInfo()->historyLength;

        yield $simple->lower($str);
        $result[] = Workflow::getInfo()->historyLength;

        return $result;
    }
}
