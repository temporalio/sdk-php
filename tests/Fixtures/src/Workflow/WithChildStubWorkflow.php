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
class WithChildStubWorkflow
{
    #[WorkflowMethod(name: 'WithChildStubWorkflow')]
    public function handler(string $input): iterable
    {
        $child = Workflow::newChildWorkflowStub(SimpleWorkflow::class);

        return 'Child: ' . (yield $child->handler('child ' . $input));
    }
}
