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
class SimpleSignalledWorkflowWithSleep
{
    private $counter = 0;

    #[Workflow\SignalMethod(name: "add")]
    public function add(
        int $value,
    ): void {
        $this->counter += $value;
    }

    #[WorkflowMethod(name: 'SimpleSignalledWorkflowWithSleep')]
    public function handler(): int
    {
        // collect signals during one second
        Workflow::timer(1);

        return $this->counter;
    }
}
