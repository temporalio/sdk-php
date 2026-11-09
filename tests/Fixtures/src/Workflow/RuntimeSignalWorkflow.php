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

        Workflow::registerSignal('add', static function ($value) use (&$counter, $wait1, $wait2): void {
            $counter += $value;
            $wait1->resolve($value);
            $wait2->resolve($value);
        });

        Workflow::await($wait1->promise());
        Workflow::await($wait2->promise());

        return $counter;
    }
}
