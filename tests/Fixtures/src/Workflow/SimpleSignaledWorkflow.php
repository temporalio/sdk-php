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
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class SimpleSignaledWorkflow
{
    private $counter = 0;

    #[SignalMethod(name: "add")]
    public function add(
        int $value
    ) {
        $this->counter += $value;
    }

    #[WorkflowMethod(name: 'SimpleSignaledWorkflow')]
    public function handler(): iterable
    {
        // collect signals during one second
        yield Workflow::timer(1);

        return $this->counter;
    }
}
