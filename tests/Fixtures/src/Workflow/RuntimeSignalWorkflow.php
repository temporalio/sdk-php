<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use React\Promise\Deferred;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class RuntimeSignalWorkflow
{
    #[WorkflowMethod]
    public function handler()
    {
        $wait1 = new Deferred();
        $wait2 = new Deferred();

        $counter = 0;

        Workflow::registerSignal('add', function ($value) use (&$counter, $wait1, $wait2) {
            $counter += $value;
            $wait1->resolve($value);
            $wait2->resolve($value);
        });

        yield $wait1;
        yield $wait2;

        return $counter;
    }
}
