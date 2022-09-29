<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface SignalledWorkflowWithInheritance extends SignalledWorkflowReusable
{
    #[WorkflowMethod(name: 'SignalledWorkflowWithInheritance')]
    public function run(
        int $count
    );
}
