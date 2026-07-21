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
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Blocks on a pure condition with no timer, so a client-side driver cannot rely on the
 * first-timer readiness heuristic and must be pointed at a query predicate instead.
 */
#[WorkflowInterface]
class SignalOnlyWorkflow
{
    private int $received = 0;
    private bool $done = false;

    #[WorkflowMethod(name: 'SignalOnlyWorkflow')]
    public function handler(): iterable
    {
        yield Workflow::await(fn(): bool => $this->done);

        return $this->received;
    }

    #[SignalMethod(name: 'ping')]
    public function ping(): void
    {
        ++$this->received;
    }

    #[SignalMethod(name: 'finish')]
    public function finish(): void
    {
        $this->done = true;
    }

    #[QueryMethod(name: 'started')]
    public function started(): bool
    {
        return true;
    }
}
