<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class SignalChildViaStubWorkflow
{
    #[WorkflowMethod(name: 'SignalChildViaStubWorkflow')]
    public function handler()
    {
        // typed stub
        $simple = Workflow::newChildWorkflowStub(SimpleSignalledWorkflow::class);

        // start execution
        $call = $simple->handler();

        yield $simple->add(8);

        // expects 8
        return yield $call;
    }
}
