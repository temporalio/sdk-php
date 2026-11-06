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

#[Workflow\WorkflowInterface]
class WithChildStubWorkflow
{
    #[WorkflowMethod(name: 'WithChildStubWorkflow')]
    public function handler(string $input): string
    {
        $child = Workflow::newChildWorkflowStub(SimpleWorkflow::class);

        return 'Child: ' . ($child->handler('child ' . $input));
    }
}
