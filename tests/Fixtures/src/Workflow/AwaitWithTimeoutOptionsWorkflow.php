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
use Temporal\Workflow\AwaitOptions;
use Temporal\Workflow\TimerOptions;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class AwaitWithTimeoutOptionsWorkflow
{
    /**
     * Awaits a condition that is never met, so the await is always settled by the timer.
     *
     * @param null|non-empty-string $summary Summary for the timer created by the await.
     *        NULL means no {@see TimerOptions} are passed.
     * @param int $timeout Await timeout in seconds.
     * @param bool $useAwaitOptions FALSE means the await is called with a plain timeout value.
     * @return \Generator<mixed, mixed, mixed, bool> FALSE because of the timeout.
     */
    #[WorkflowMethod]
    public function handler(?string $summary = null, int $timeout = 1, bool $useAwaitOptions = true)
    {
        $intervalOrOptions = $useAwaitOptions
            ? AwaitOptions::new($timeout)->withTimerOptions(
                $summary === null ? null : TimerOptions::new()->withSummary($summary),
            )
            : $timeout;

        return yield Workflow::awaitWithTimeout($intervalOrOptions, static fn(): bool => false);
    }
}
