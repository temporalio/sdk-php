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

#[Workflow\WorkflowInterface]
class WorkflowWithSequence
{
    #[WorkflowMethod(name: 'WorkflowWithSequence')]
    public function handler()
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5),
        );

        $a = Workflow::async(static fn() => $simple->echo('a'));
        $b = Workflow::async(static fn() => $simple->echo('b'));

        Workflow::all([$a, $b]);

        return 'OK';
    }
}
