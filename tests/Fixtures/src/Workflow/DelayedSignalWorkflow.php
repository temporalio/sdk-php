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
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class DelayedSignalWorkflow
{
    private ?string $received = null;

    #[WorkflowMethod(name: 'DelayedSignalWorkflow')]
    public function handler(int $timeoutSeconds): iterable
    {
        $arrived = yield Workflow::awaitWithTimeout($timeoutSeconds, fn(): bool => $this->received !== null);

        return $arrived ? 'signal:' . $this->received : 'timeout';
    }

    #[SignalMethod(name: 'unblock')]
    public function unblock(string $value): void
    {
        $this->received = $value;
    }
}
