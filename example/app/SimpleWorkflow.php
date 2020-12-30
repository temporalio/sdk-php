<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use Temporal\Activity\ActivityOptions;
use Temporal\Common\MethodRetry;
use Temporal\Common\Uuid;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowMethod;

class SimpleWorkflow
{
    #[WorkflowMethod(name: 'SimpleWorkflow')]
    #[MethodRetry(initialInterval: '10s')]
    public function handler(string $input): iterable
    {
        $activities = Workflow::newActivityStub(SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(10)
        );

        $actual = yield Workflow::sideEffect(fn() => Uuid::v4());

        dump('Actual UUID: ' . $actual);

        $result = yield $activities->echo($actual);

        dump('Returned UUID: ' . $result);

        return $input;
    }

    #[SignalMethod(name: 'query')]
    public function query()
    {
        return 42;
    }

    #[WorkflowMethod(name: 'ChildWorkflow')]
    public function child(): iterable
    {
        $result = yield Workflow::executeChildWorkflow('SimpleWorkflow', [
            'hell or world'
        ]);

        dump('CHILD Workflow Returned: ' . $result);
    }
}
