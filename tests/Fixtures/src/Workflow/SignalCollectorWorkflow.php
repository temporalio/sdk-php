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

#[WorkflowInterface]
class SignalCollectorWorkflow
{
    /** @var list<array{value: string, at: int}> */
    private array $events = [];
    private bool $done = false;

    /**
     * @return iterable<mixed>
     */
    #[WorkflowMethod(name: 'SignalCollectorWorkflow')]
    public function handler(int $maxSeconds): iterable
    {
        yield Workflow::awaitWithTimeout($maxSeconds, fn(): bool => $this->done);

        return $this->events;
    }

    #[SignalMethod(name: 'push')]
    public function push(string $value): void
    {
        $this->events[] = ['value' => $value, 'at' => Workflow::now()->getTimestamp()];
    }

    #[SignalMethod(name: 'finish')]
    public function finish(): void
    {
        $this->done = true;
    }

    #[QueryMethod(name: 'count')]
    public function count(): int
    {
        return \count($this->events);
    }
}
