<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Promise;
use Temporal\Workflow;
use Temporal\Workflow\AwaitOptions;
use Temporal\Workflow\TimerOptions;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ConcurrentAwaitWithTimeoutOptionsWorkflow
{
    /**
     * Runs two concurrent awaits, each with its own timer summary.
     *
     * @param non-empty-string $first Summary for the timer of the first await.
     * @param non-empty-string $second Summary for the timer of the second await.
     * @return array{bool, bool} Both awaits are settled by timers.
     */
    #[WorkflowMethod]
    public function handler(string $first, string $second)
    {
        return Workflow::await(Promise::all([
            $this->await($first, 1),
            $this->await($second, 2),
        ]));
    }

    /**
     * @param non-empty-string $summary
     */
    private function await(string $summary, int $timeout): PromiseInterface
    {
        return Workflow::async(static function () use ($summary, $timeout): bool {
            return Workflow::awaitWithTimeout(
                AwaitOptions::new($timeout)->withTimerOptions(
                    TimerOptions::new()->withSummary($summary),
                ),
                static fn(): bool => false,
            );
        });
    }
}
